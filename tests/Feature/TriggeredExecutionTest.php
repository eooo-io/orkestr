<?php

use App\Jobs\RunScheduledAgentJob;
use App\Models\Agent;
use App\Models\AgentSchedule;
use App\Models\ExecutionRun;
use App\Models\Project;
use App\Models\User;
use App\Services\Execution\AgentExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create(['name' => 'Trigger Test', 'path' => '/tmp/trigger-test']);
    $this->agent = Agent::create([
        'name' => 'Trigger Agent',
        'slug' => 'trigger-agent',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-6',
        'base_instructions' => 'You are a test agent.',
    ]);

    $this->project->agents()->attach($this->agent->id, ['is_enabled' => true]);
});

// --- #387: Webhook triggers agent execution ---

test('generic webhook triggers agent execution for project', function () {
    Queue::fake();

    AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Webhook Trigger',
        'trigger_type' => 'webhook',
        'is_enabled' => true,
    ]);

    $response = $this->postJson("/api/webhooks/inbound/{$this->project->id}", [
        'event' => 'deploy',
        'data' => ['env' => 'production'],
    ]);

    $response->assertStatus(202);
    $response->assertJsonPath('executions_triggered', 1);

    Queue::assertPushed(RunScheduledAgentJob::class, function ($job) {
        return $job->triggerSource === 'webhook';
    });
});

test('generic webhook returns empty when no webhook schedules exist', function () {
    $response = $this->postJson("/api/webhooks/inbound/{$this->project->id}", [
        'data' => 'test',
    ]);

    $response->assertOk();
    $response->assertJsonPath('executions_triggered', 0);
});

test('generic webhook skips disabled webhook schedules', function () {
    Queue::fake();

    AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Disabled Webhook',
        'trigger_type' => 'webhook',
        'is_enabled' => false,
    ]);

    $response = $this->postJson("/api/webhooks/inbound/{$this->project->id}", [
        'data' => 'test',
    ]);

    $response->assertOk();
    $response->assertJsonPath('executions_triggered', 0);
    Queue::assertNothingPushed();
});

test('github webhook also triggers webhook-scheduled agents', function () {
    Queue::fake();

    AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'GitHub Triggered',
        'trigger_type' => 'webhook',
        'is_enabled' => true,
    ]);

    $response = $this->postJson(
        "/api/webhooks/github/{$this->project->id}",
        ['ref' => 'refs/heads/main'],
        ['X-GitHub-Event' => 'push']
    );

    $response->assertOk();
    $response->assertJsonPath('executions_triggered', 1);

    Queue::assertPushed(RunScheduledAgentJob::class, function ($job) {
        return $job->triggerSource === 'webhook';
    });
});

// --- #388: A2A-triggered execution ---

test('A2A sync execution returns result', function () {
    $mockRun = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
        'output' => ['result' => 'Task done'],
        'total_tokens' => 100,
        'total_duration_ms' => 2000,
    ]);

    $mockService = Mockery::mock(AgentExecutionService::class);
    $mockService->shouldReceive('execute')
        ->once()
        ->andReturn($mockRun);

    app()->instance(AgentExecutionService::class, $mockService);

    $response = $this->postJson('/api/a2a/trigger-agent/execute', [
        'task' => 'Summarize this document',
        'context' => ['doc_id' => '123'],
    ], [
        'X-A2A-Mode' => 'sync',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'completed');
    $response->assertJsonPath('output.result', 'Task done');
    expect($response->json('execution_id'))->not->toBeNull();
});

test('A2A async execution returns execution ID', function () {
    $response = $this->postJson('/api/a2a/trigger-agent/execute', [
        'task' => 'Analyze this data',
    ], [
        'X-A2A-Mode' => 'async',
    ]);

    $response->assertStatus(202);
    $response->assertJsonPath('status', 'pending');
    $response->assertJsonPath('message', 'Execution dispatched.');
    expect($response->json('execution_id'))->not->toBeNull();

    // Verify at least one execution run was created (the pre-created pending one)
    // Note: the afterResponse closure may create a second run via AgentExecutionService
    expect(ExecutionRun::count())->toBeGreaterThanOrEqual(1);
});

test('A2A execution defaults to async mode', function () {
    $response = $this->postJson('/api/a2a/trigger-agent/execute', [
        'task' => 'Do something',
    ]);

    $response->assertStatus(202);
    $response->assertJsonPath('status', 'pending');
});

test('A2A validates agent exists', function () {
    $response = $this->postJson('/api/a2a/nonexistent-agent/execute', [
        'task' => 'Test',
    ]);

    $response->assertStatus(404);
    $response->assertJsonPath('error', 'Agent not found.');
});

test('A2A validates agent is enabled in a project', function () {
    $disabledAgent = Agent::create([
        'name' => 'Disabled A2A Agent',
        'slug' => 'disabled-a2a-agent',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-6',
        'base_instructions' => 'Test',
    ]);

    // Agent exists but not attached to any project
    $response = $this->postJson('/api/a2a/disabled-a2a-agent/execute', [
        'task' => 'Test',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error', 'Agent is not enabled in any project.');
});

test('A2A validates task is required', function () {
    $response = $this->postJson('/api/a2a/trigger-agent/execute', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['task']);
});

test('A2A sync execution handles execution failure gracefully', function () {
    $mockService = Mockery::mock(AgentExecutionService::class);
    $mockService->shouldReceive('execute')
        ->once()
        ->andThrow(new \RuntimeException('LLM provider unavailable'));

    app()->instance(AgentExecutionService::class, $mockService);

    $response = $this->postJson('/api/a2a/trigger-agent/execute', [
        'task' => 'This will fail',
    ], [
        'X-A2A-Mode' => 'sync',
    ]);

    $response->assertStatus(500);
    $response->assertJsonPath('error', 'Execution failed.');
});

// --- #387: RunScheduledAgentJob records trigger_type ---

test('RunScheduledAgentJob sets trigger_type on execution run', function () {
    $schedule = AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Trigger Type Test',
        'trigger_type' => 'cron',
        'cron_expression' => '*/5 * * * *',
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
        ->andReturn($mockRun);

    $job = new RunScheduledAgentJob($schedule, [], 'webhook');
    $job->handle($mockService);

    $mockRun->refresh();
    expect($mockRun->trigger_type)->toBe('webhook');
    expect($mockRun->schedule_id)->toBe($schedule->id);
});
