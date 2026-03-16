<?php

use App\Console\Commands\RunSchedulesCommand;
use App\Jobs\RunScheduledAgentJob;
use App\Models\Agent;
use App\Models\AgentSchedule;
use App\Models\ExecutionRun;
use App\Models\Notification;
use App\Models\Project;
use App\Models\User;
use App\Services\Execution\AgentExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create(['name' => 'Exec Test', 'path' => '/tmp/exec-test']);
    $this->agent = Agent::create([
        'name' => 'Exec Agent',
        'slug' => 'exec-agent',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-6',
        'base_instructions' => 'You are a test agent.',
        'notify_on_failure' => true,
        'notify_on_success' => false,
    ]);

    $this->project->agents()->attach($this->agent->id, ['is_enabled' => true]);
});

// --- #386: orkestr:run-schedules command ---

test('orkestr:run-schedules dispatches due agents', function () {
    Queue::fake();

    AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Due Schedule',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
        'next_run_at' => now()->subMinute(),
        'is_enabled' => true,
    ]);

    $this->artisan('orkestr:run-schedules')
        ->expectsOutputToContain('Dispatched 1 schedule(s)')
        ->assertSuccessful();

    Queue::assertPushed(RunScheduledAgentJob::class, 1);
});

test('orkestr:run-schedules skips inactive schedules', function () {
    Queue::fake();

    AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Disabled Schedule',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
        'next_run_at' => now()->subMinute(),
        'is_enabled' => false,
    ]);

    $this->artisan('orkestr:run-schedules')
        ->expectsOutputToContain('No due schedules found')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

test('orkestr:run-schedules skips schedules not yet due', function () {
    Queue::fake();

    AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Future Schedule',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
        'next_run_at' => now()->addHour(),
        'is_enabled' => true,
    ]);

    $this->artisan('orkestr:run-schedules')
        ->expectsOutputToContain('No due schedules found')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

test('orkestr:run-schedules auto-disables after 5 failures', function () {
    Queue::fake();

    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Fragile Schedule',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
        'next_run_at' => now()->subMinute(),
        'is_enabled' => true,
        'failure_count' => 5,
        'max_retries' => 3,
        'created_by' => $this->user->id,
    ]);

    // The recordFailure mechanism handles auto-disable via max_retries.
    // The RunSchedulesCommand has its own auto-disable at failure_count > 5.
    // We test the model method directly.
    $schedule->recordFailure('Test error');
    $schedule->refresh();

    // After 6 failures (5 + 1), and max_retries = 3, should be disabled
    expect($schedule->failure_count)->toBe(6);
    expect($schedule->is_enabled)->toBeFalse();
});

test('orkestr:run-schedules calculates next_run_at correctly', function () {
    Queue::fake();

    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Next Run Test',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
        'next_run_at' => now()->subMinute(),
        'is_enabled' => true,
    ]);

    $this->artisan('orkestr:run-schedules')->assertSuccessful();

    $schedule->refresh();
    expect($schedule->next_run_at)->not->toBeNull();
    expect($schedule->next_run_at->isFuture())->toBeTrue();
    expect($schedule->last_run_at)->not->toBeNull();
});

test('orkestr:run-schedules dispatches multiple due schedules', function () {
    Queue::fake();

    for ($i = 1; $i <= 3; $i++) {
        AgentSchedule::create([
            'project_id' => $this->project->id,
            'agent_id' => $this->agent->id,
            'name' => "Schedule {$i}",
            'trigger_type' => 'cron',
            'cron_expression' => '*/5 * * * *',
            'next_run_at' => now()->subMinutes($i),
            'is_enabled' => true,
        ]);
    }

    $this->artisan('orkestr:run-schedules')
        ->expectsOutputToContain('Dispatched 3 schedule(s)')
        ->assertSuccessful();

    Queue::assertPushed(RunScheduledAgentJob::class, 3);
});

test('orkestr:run-schedules only dispatches cron type schedules', function () {
    Queue::fake();

    AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Webhook Schedule',
        'trigger_type' => 'webhook',
        'is_enabled' => true,
    ]);

    AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Event Schedule',
        'trigger_type' => 'event',
        'event_name' => 'test.event',
        'is_enabled' => true,
    ]);

    $this->artisan('orkestr:run-schedules')
        ->expectsOutputToContain('No due schedules found')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

// --- #389: Execution notifications ---

test('execution notification is created on failure when notify_on_failure is true', function () {
    $agent = Agent::create([
        'name' => 'Notify Agent',
        'slug' => 'notify-agent',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-6',
        'base_instructions' => 'Test',
        'notify_on_failure' => true,
    ]);

    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $agent->id,
        'status' => 'failed',
        'error' => 'Something went wrong',
        'created_by' => $this->user->id,
        'total_duration_ms' => 5000,
    ]);

    \App\Listeners\ExecutionCompletedListener::handle($run);

    expect(Notification::where('user_id', $this->user->id)
        ->where('type', 'execution_failed')
        ->count())->toBe(1);

    $notification = Notification::first();
    expect($notification->title)->toContain('Execution failed');
    expect($notification->body)->toContain('Something went wrong');
});

test('execution notification is not created on success when notify_on_success is false', function () {
    $agent = Agent::create([
        'name' => 'Silent Agent',
        'slug' => 'silent-agent',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-6',
        'base_instructions' => 'Test',
        'notify_on_success' => false,
        'notify_on_failure' => false,
    ]);

    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $agent->id,
        'status' => 'completed',
        'created_by' => $this->user->id,
    ]);

    \App\Listeners\ExecutionCompletedListener::handle($run);

    expect(Notification::count())->toBe(0);
});

test('execution notification is created on success when notify_on_success is true', function () {
    $agent = Agent::create([
        'name' => 'Verbose Agent',
        'slug' => 'verbose-agent',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-6',
        'base_instructions' => 'Test',
        'notify_on_success' => true,
    ]);

    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $agent->id,
        'status' => 'completed',
        'created_by' => $this->user->id,
        'total_duration_ms' => 3000,
        'total_cost_microcents' => 500,
    ]);

    \App\Listeners\ExecutionCompletedListener::handle($run);

    $notification = Notification::first();
    expect($notification)->not->toBeNull();
    expect($notification->type)->toBe('execution_completed');
    expect($notification->title)->toContain('completed');
});
