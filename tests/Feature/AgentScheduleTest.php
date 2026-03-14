<?php

use App\Jobs\RunScheduledAgentJob;
use App\Models\Agent;
use App\Models\AgentSchedule;
use App\Models\ExecutionRun;
use App\Models\Project;
use App\Models\User;
use App\Services\Execution\AgentExecutionService;
use App\Services\ScheduleTriggerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create(['name' => 'Schedule Test', 'path' => '/tmp/schedule-test']);
    $this->agent = Agent::create([
        'name' => 'Schedule Agent',
        'slug' => 'schedule-agent',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-6',
        'base_instructions' => 'You are a test agent.',
    ]);

    $this->project->agents()->attach($this->agent->id, ['is_enabled' => true]);
});

// --- Model tests ---

test('AgentSchedule auto-generates UUID on creation', function () {
    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Test Schedule',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
    ]);

    expect($schedule->uuid)->not->toBeNull();
    expect(strlen($schedule->uuid))->toBe(36);
});

test('AgentSchedule auto-generates webhook_token for webhook type', function () {
    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Webhook Schedule',
        'trigger_type' => 'webhook',
    ]);

    expect($schedule->webhook_token)->not->toBeNull();
    expect(strlen($schedule->webhook_token))->toBe(48);
});

test('AgentSchedule does not generate webhook_token for cron type', function () {
    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Cron Schedule',
        'trigger_type' => 'cron',
        'cron_expression' => '0 * * * *',
    ]);

    expect($schedule->webhook_token)->toBeNull();
});

test('AgentSchedule computes next_run_at for cron type on creation', function () {
    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Cron Schedule',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
    ]);

    expect($schedule->next_run_at)->not->toBeNull();
    expect($schedule->next_run_at->isFuture())->toBeTrue();
});

test('AgentSchedule computeNextRun returns valid date', function () {
    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Compute Test',
        'trigger_type' => 'cron',
        'cron_expression' => '0 0 * * *', // daily at midnight
    ]);

    $nextRun = $schedule->computeNextRun();
    expect($nextRun)->not->toBeNull();
    expect($nextRun->isFuture())->toBeTrue();
});

test('AgentSchedule enabled scope filters correctly', function () {
    AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Enabled',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
        'is_enabled' => true,
    ]);

    AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Disabled',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
        'is_enabled' => false,
    ]);

    expect(AgentSchedule::enabled()->count())->toBe(1);
    expect(AgentSchedule::enabled()->first()->name)->toBe('Enabled');
});

test('AgentSchedule due scope filters correctly', function () {
    AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Due',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
        'next_run_at' => now()->subMinute(),
        'is_enabled' => true,
    ]);

    AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Not Due',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
        'next_run_at' => now()->addHour(),
        'is_enabled' => true,
    ]);

    expect(AgentSchedule::due()->count())->toBe(1);
    expect(AgentSchedule::due()->first()->name)->toBe('Due');
});

test('AgentSchedule cron scope filters correctly', function () {
    AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Cron',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
    ]);

    AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Webhook',
        'trigger_type' => 'webhook',
    ]);

    AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Event',
        'trigger_type' => 'event',
        'event_name' => 'test.event',
    ]);

    expect(AgentSchedule::cron()->count())->toBe(1);
    expect(AgentSchedule::webhook()->count())->toBe(1);
    expect(AgentSchedule::event()->count())->toBe(1);
});

test('AgentSchedule recordSuccess resets failure count', function () {
    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Success Test',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
        'failure_count' => 3,
        'last_error' => 'previous error',
    ]);

    $schedule->recordSuccess();
    $schedule->refresh();

    expect($schedule->failure_count)->toBe(0);
    expect($schedule->last_error)->toBeNull();
    expect($schedule->run_count)->toBe(1);
    expect($schedule->last_run_at)->not->toBeNull();
});

test('AgentSchedule recordFailure disables after max_retries', function () {
    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Failure Test',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
        'max_retries' => 2,
    ]);

    $schedule->recordFailure('Error 1');
    $schedule->refresh();
    expect($schedule->is_enabled)->toBeTrue();
    expect($schedule->failure_count)->toBe(1);

    $schedule->recordFailure('Error 2');
    $schedule->refresh();
    expect($schedule->is_enabled)->toBeFalse();
    expect($schedule->failure_count)->toBe(2);
    expect($schedule->last_error)->toBe('Error 2');
});

test('AgentSchedule relationships work', function () {
    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Relationship Test',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
        'created_by' => $this->user->id,
    ]);

    expect($schedule->project->id)->toBe($this->project->id);
    expect($schedule->agent->id)->toBe($this->agent->id);
    expect($schedule->creator->id)->toBe($this->user->id);
});

test('ExecutionRun has schedule relationship', function () {
    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Run Relationship',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
    ]);

    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'schedule_id' => $schedule->id,
    ]);

    expect($run->schedule->id)->toBe($schedule->id);
    expect($schedule->executionRuns->count())->toBe(1);
});

// --- API tests ---

test('GET /api/projects/{project}/schedules returns project schedules', function () {
    AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Test Schedule',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
    ]);

    $response = $this->getJson("/api/projects/{$this->project->id}/schedules");

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.name', 'Test Schedule');
    $response->assertJsonPath('data.0.trigger_type', 'cron');
});

test('POST /api/projects/{project}/schedules creates cron schedule', function () {
    $response = $this->postJson("/api/projects/{$this->project->id}/schedules", [
        'name' => 'Daily Report',
        'agent_id' => $this->agent->id,
        'trigger_type' => 'cron',
        'cron_expression' => '0 9 * * *',
        'timezone' => 'America/New_York',
        'input_template' => ['goal' => 'Generate daily report'],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Daily Report');
    $response->assertJsonPath('data.trigger_type', 'cron');
    $response->assertJsonPath('data.cron_expression', '0 9 * * *');
    $response->assertJsonPath('data.timezone', 'America/New_York');

    expect(AgentSchedule::count())->toBe(1);
});

test('POST /api/projects/{project}/schedules creates webhook schedule', function () {
    $response = $this->postJson("/api/projects/{$this->project->id}/schedules", [
        'name' => 'Webhook Trigger',
        'agent_id' => $this->agent->id,
        'trigger_type' => 'webhook',
        'webhook_secret' => 'my-secret',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.trigger_type', 'webhook');
    expect($response->json('data.webhook_token'))->not->toBeNull();
    expect($response->json('data.webhook_url'))->toContain('/api/webhooks/schedule/');
});

test('POST /api/projects/{project}/schedules creates event schedule', function () {
    $response = $this->postJson("/api/projects/{$this->project->id}/schedules", [
        'name' => 'On Deployment',
        'agent_id' => $this->agent->id,
        'trigger_type' => 'event',
        'event_name' => 'deployment.completed',
        'event_filters' => ['environment' => 'production'],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.trigger_type', 'event');
    $response->assertJsonPath('data.event_name', 'deployment.completed');
});

test('POST /api/projects/{project}/schedules validates required fields', function () {
    $response = $this->postJson("/api/projects/{$this->project->id}/schedules", []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['name', 'agent_id', 'trigger_type']);
});

test('POST /api/projects/{project}/schedules validates cron expression', function () {
    $response = $this->postJson("/api/projects/{$this->project->id}/schedules", [
        'name' => 'Bad Cron',
        'agent_id' => $this->agent->id,
        'trigger_type' => 'cron',
        'cron_expression' => 'not-a-cron',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['cron_expression']);
});

test('POST /api/projects/{project}/schedules requires event_name for event type', function () {
    $response = $this->postJson("/api/projects/{$this->project->id}/schedules", [
        'name' => 'Missing Event',
        'agent_id' => $this->agent->id,
        'trigger_type' => 'event',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['event_name']);
});

test('PUT /api/schedules/{schedule} updates schedule', function () {
    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Original Name',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
    ]);

    $response = $this->putJson("/api/schedules/{$schedule->id}", [
        'name' => 'Updated Name',
        'cron_expression' => '0 */2 * * *',
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.name', 'Updated Name');
    $response->assertJsonPath('data.cron_expression', '0 */2 * * *');
});

test('POST /api/schedules/{schedule}/toggle toggles enabled state', function () {
    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Toggle Test',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
        'is_enabled' => true,
    ]);

    $response = $this->postJson("/api/schedules/{$schedule->id}/toggle");

    $response->assertOk();
    $response->assertJsonPath('data.is_enabled', false);

    // Toggle back
    $response = $this->postJson("/api/schedules/{$schedule->id}/toggle");
    $response->assertJsonPath('data.is_enabled', true);
});

test('POST /api/schedules/{schedule}/trigger dispatches job', function () {
    Queue::fake();

    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Manual Trigger',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
    ]);

    $response = $this->postJson("/api/schedules/{$schedule->id}/trigger");

    $response->assertOk();
    $response->assertJsonPath('message', 'Schedule triggered');

    Queue::assertPushed(RunScheduledAgentJob::class, function ($job) use ($schedule) {
        return $job->schedule->id === $schedule->id && $job->triggerSource === 'manual';
    });
});

test('DELETE /api/schedules/{schedule} deletes schedule', function () {
    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Delete Me',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
    ]);

    $response = $this->deleteJson("/api/schedules/{$schedule->id}");

    $response->assertOk();
    expect(AgentSchedule::count())->toBe(0);
});

test('GET /api/schedules/{schedule}/runs returns execution runs', function () {
    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Runs Test',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
    ]);

    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'schedule_id' => $schedule->id,
        'status' => 'completed',
    ]);

    $response = $this->getJson("/api/schedules/{$schedule->id}/runs");

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
});

// --- Job tests ---

test('RunScheduledAgentJob executes agent and records success', function () {
    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Job Test',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
        'input_template' => ['goal' => 'test'],
        'created_by' => $this->user->id,
    ]);

    $mockRun = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
    ]);

    $mockService = Mockery::mock(AgentExecutionService::class);
    $mockService->shouldReceive('execute')
        ->once()
        ->withArgs(function ($project, $agent, $input, $config, $createdBy) use ($schedule) {
            return $project->id === $schedule->project_id
                && $agent->id === $schedule->agent_id
                && $input['goal'] === 'test'
                && $createdBy === $schedule->created_by;
        })
        ->andReturn($mockRun);

    app()->instance(AgentExecutionService::class, $mockService);

    $job = new RunScheduledAgentJob($schedule, [], 'cron');
    $job->handle($mockService);

    $schedule->refresh();
    expect($schedule->run_count)->toBe(1);
    expect($schedule->failure_count)->toBe(0);
    expect($schedule->last_error)->toBeNull();
    expect($schedule->last_run_at)->not->toBeNull();

    $mockRun->refresh();
    expect($mockRun->schedule_id)->toBe($schedule->id);
});

test('RunScheduledAgentJob skips disabled schedule', function () {
    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Disabled Job',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
        'is_enabled' => false,
    ]);

    $mockService = Mockery::mock(AgentExecutionService::class);
    $mockService->shouldNotReceive('execute');

    $job = new RunScheduledAgentJob($schedule);
    $job->handle($mockService);

    $schedule->refresh();
    expect($schedule->run_count)->toBe(0);
});

test('RunScheduledAgentJob failed method records failure', function () {
    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Failure Job',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
        'max_retries' => 3,
    ]);

    $job = new RunScheduledAgentJob($schedule);
    $job->failed(new \RuntimeException('Something went wrong'));

    $schedule->refresh();
    expect($schedule->failure_count)->toBe(1);
    expect($schedule->last_error)->toBe('Something went wrong');
    expect($schedule->is_enabled)->toBeTrue(); // Still under max_retries
});

test('RunScheduledAgentJob merges trigger payload with input template', function () {
    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Merge Test',
        'trigger_type' => 'webhook',
        'input_template' => ['base_goal' => 'analyze'],
    ]);

    $mockRun = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
    ]);

    $mockService = Mockery::mock(AgentExecutionService::class);
    $mockService->shouldReceive('execute')
        ->once()
        ->withArgs(function ($project, $agent, $input) {
            return $input['base_goal'] === 'analyze'
                && $input['webhook_data'] === 'test'
                && $input['_trigger_source'] === 'webhook';
        })
        ->andReturn($mockRun);

    $job = new RunScheduledAgentJob($schedule, ['webhook_data' => 'test'], 'webhook');
    $job->handle($mockService);
});

// --- Webhook trigger tests ---

test('POST /api/webhooks/schedule/{token} dispatches job', function () {
    Queue::fake();

    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Webhook Test',
        'trigger_type' => 'webhook',
    ]);

    $response = $this->postJson("/api/webhooks/schedule/{$schedule->webhook_token}", [
        'message' => 'Hello from webhook',
    ]);

    $response->assertStatus(202);

    Queue::assertPushed(RunScheduledAgentJob::class, function ($job) use ($schedule) {
        return $job->schedule->id === $schedule->id && $job->triggerSource === 'webhook';
    });
});

test('POST /api/webhooks/schedule/{token} validates HMAC signature', function () {
    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'HMAC Test',
        'trigger_type' => 'webhook',
        'webhook_secret' => 'test-secret',
    ]);

    // Missing signature
    $response = $this->postJson("/api/webhooks/schedule/{$schedule->webhook_token}", ['data' => 'test']);
    $response->assertStatus(403);

    // Invalid signature
    $response = $this->postJson(
        "/api/webhooks/schedule/{$schedule->webhook_token}",
        ['data' => 'test'],
        ['X-Signature-256' => 'sha256=invalid']
    );
    $response->assertStatus(403);
});

test('POST /api/webhooks/schedule/{token} accepts valid HMAC signature', function () {
    Queue::fake();

    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Valid HMAC',
        'trigger_type' => 'webhook',
        'webhook_secret' => 'test-secret',
    ]);

    $payload = json_encode(['data' => 'test']);
    $signature = 'sha256=' . hash_hmac('sha256', $payload, 'test-secret');

    $response = $this->call('POST', "/api/webhooks/schedule/{$schedule->webhook_token}", [], [], [], [
        'HTTP_X-Signature-256' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $response->assertStatus(202);
});

test('POST /api/webhooks/schedule/{token} rejects disabled schedule', function () {
    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Disabled Webhook',
        'trigger_type' => 'webhook',
        'is_enabled' => false,
    ]);

    $response = $this->postJson("/api/webhooks/schedule/{$schedule->webhook_token}");
    $response->assertStatus(422);
});

test('POST /api/webhooks/schedule/{token} returns 404 for invalid token', function () {
    $response = $this->postJson('/api/webhooks/schedule/invalid-token-12345');
    $response->assertStatus(404);
});

// --- Event trigger service tests ---

test('ScheduleTriggerService dispatches matching event schedules', function () {
    Queue::fake();

    AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Matching Event',
        'trigger_type' => 'event',
        'event_name' => 'deployment.completed',
        'is_enabled' => true,
    ]);

    AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Non-Matching Event',
        'trigger_type' => 'event',
        'event_name' => 'build.started',
        'is_enabled' => true,
    ]);

    ScheduleTriggerService::trigger('deployment.completed', ['env' => 'prod']);

    Queue::assertPushed(RunScheduledAgentJob::class, 1);
});

test('ScheduleTriggerService skips disabled event schedules', function () {
    Queue::fake();

    AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Disabled Event',
        'trigger_type' => 'event',
        'event_name' => 'deployment.completed',
        'is_enabled' => false,
    ]);

    ScheduleTriggerService::trigger('deployment.completed', []);

    Queue::assertNothingPushed();
});

test('ScheduleTriggerService respects event_filters', function () {
    Queue::fake();

    AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Filtered Event',
        'trigger_type' => 'event',
        'event_name' => 'deployment.completed',
        'event_filters' => ['environment' => 'production'],
        'is_enabled' => true,
    ]);

    // Should not trigger - wrong environment
    ScheduleTriggerService::trigger('deployment.completed', ['environment' => 'staging']);
    Queue::assertNothingPushed();

    // Should trigger - matching environment
    ScheduleTriggerService::trigger('deployment.completed', ['environment' => 'production']);
    Queue::assertPushed(RunScheduledAgentJob::class, 1);
});

// --- Command tests ---

test('schedules:process dispatches due schedules', function () {
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

    AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Not Due Schedule',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
        'next_run_at' => now()->addHour(),
        'is_enabled' => true,
    ]);

    $this->artisan('schedules:process')
        ->expectsOutputToContain('Dispatched 1 schedule(s)')
        ->assertSuccessful();

    Queue::assertPushed(RunScheduledAgentJob::class, 1);
});

test('schedules:process skips disabled schedules', function () {
    Queue::fake();

    AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Disabled Due',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
        'next_run_at' => now()->subMinute(),
        'is_enabled' => false,
    ]);

    $this->artisan('schedules:process')
        ->expectsOutputToContain('No due schedules found')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

test('schedules:process updates next_run_at before dispatching', function () {
    Queue::fake();

    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Update Next Run',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
        'next_run_at' => now()->subMinute(),
        'is_enabled' => true,
    ]);

    $this->artisan('schedules:process')->assertSuccessful();

    $schedule->refresh();
    expect($schedule->next_run_at->isFuture())->toBeTrue();
});
