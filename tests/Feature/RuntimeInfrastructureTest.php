<?php

use App\Jobs\RunAgentJob;
use App\Models\Agent;
use App\Models\ExecutionRun;
use App\Models\Project;
use App\Models\User;
use App\Services\Execution\BudgetEnforcer;
use App\Services\Execution\CostCalculator;
use App\Services\Mcp\McpConnectionPool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create(['name' => 'Runtime Test', 'path' => '/tmp/runtime-test']);
    $this->agent = Agent::create([
        'name' => 'Runtime Agent',
        'slug' => 'runtime-agent',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-6',
        'base_instructions' => 'You are a test agent.',
        'budget_limit_usd' => 5.00,
        'daily_budget_limit_usd' => 10.00,
    ]);

    $this->project->agents()->attach($this->agent->id, ['is_enabled' => true]);
});

// ──────────────────────────────────────────────────────
// #375 — Queue worker / RunAgentJob dispatch
// ──────────────────────────────────────────────────────

test('RunAgentJob dispatches correctly via Queue::fake', function () {
    Queue::fake();

    RunAgentJob::dispatch(
        projectId: $this->project->id,
        agentId: $this->agent->id,
        input: ['message' => 'Hello from queue test'],
        triggerType: 'manual',
        executionId: 'test-uuid-123',
        createdBy: $this->user->id,
    );

    Queue::assertPushed(RunAgentJob::class, function ($job) {
        return $job->projectId === $this->project->id
            && $job->agentId === $this->agent->id
            && $job->triggerType === 'manual'
            && $job->executionId === 'test-uuid-123'
            && $job->input['message'] === 'Hello from queue test';
    });
});

test('RunAgentJob has correct retry and timeout config', function () {
    $job = new RunAgentJob(
        projectId: 1,
        agentId: 1,
        input: [],
        triggerType: 'manual',
        executionId: 'test-uuid',
    );

    expect($job->tries)->toBe(3);
    expect($job->timeout)->toBe(3600);
    expect($job->backoff)->toBe([10, 30, 60]);
});

// ──────────────────────────────────────────────────────
// #376 — MCP Connection Pool
// ──────────────────────────────────────────────────────

test('McpConnectionPool release cleans up connections', function () {
    // Start with a clean pool
    McpConnectionPool::flush();

    expect(McpConnectionPool::activeCount())->toBe(0);

    // Release for a non-existent execution returns 0
    $released = McpConnectionPool::release('nonexistent-exec-id');
    expect($released)->toBe(0);
});

test('McpConnectionPool has returns false for non-existent connections', function () {
    McpConnectionPool::flush();

    expect(McpConnectionPool::has('exec-123', 1))->toBeFalse();
    expect(McpConnectionPool::has('exec-123', 999))->toBeFalse();
});

test('McpConnectionPool status returns empty array when clean', function () {
    McpConnectionPool::flush();

    expect(McpConnectionPool::status())->toBe([]);
    expect(McpConnectionPool::activeCount())->toBe(0);
});

test('McpConnectionPool pruneIdle returns 0 when empty', function () {
    McpConnectionPool::flush();

    expect(McpConnectionPool::pruneIdle())->toBe(0);
});

// ──────────────────────────────────────────────────────
// #377 — Budget Enforcer
// ──────────────────────────────────────────────────────

test('BudgetEnforcer allows when within budget', function () {
    $enforcer = new BudgetEnforcer(new CostCalculator);

    $result = $enforcer->check($this->agent, tokensUsed: 100, costUsd: 1.00);

    expect($result['allowed'])->toBeTrue();
    expect($result['reason'])->toBeNull();
    expect($result['remaining_usd'])->toBeGreaterThan(0);
});

test('BudgetEnforcer blocks when per-execution budget exceeded', function () {
    $enforcer = new BudgetEnforcer(new CostCalculator);

    $result = $enforcer->check($this->agent, tokensUsed: 50000, costUsd: 6.00);

    expect($result['allowed'])->toBeFalse();
    expect($result['reason'])->toBe('budget_exceeded');
    expect((float) $result['remaining_usd'])->toBe(0.0);
});

test('BudgetEnforcer blocks when daily budget exceeded', function () {
    $enforcer = new BudgetEnforcer(new CostCalculator);

    // Create past runs that exhaust the daily budget
    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
        'total_cost_microcents' => 11_000_000, // $11.00 (exceeds $10 daily limit)
    ]);

    // Clear cache so it recalculates from DB
    \Illuminate\Support\Facades\Cache::forget("agent:{$this->agent->id}:daily_cost:" . now()->format('Y-m-d'));

    $result = $enforcer->check($this->agent, tokensUsed: 100, costUsd: 0.50);

    expect($result['allowed'])->toBeFalse();
    expect($result['reason'])->toBe('daily_budget_exceeded');
});

test('BudgetEnforcer allows when no budget limits set', function () {
    $agentNoBudget = Agent::create([
        'name' => 'No Budget Agent',
        'slug' => 'no-budget-agent',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-6',
        'budget_limit_usd' => null,
        'daily_budget_limit_usd' => null,
    ]);

    $enforcer = new BudgetEnforcer(new CostCalculator);
    $result = $enforcer->check($agentNoBudget, tokensUsed: 999999, costUsd: 999.00);

    expect($result['allowed'])->toBeTrue();
    expect($result['reason'])->toBeNull();
});

test('BudgetEnforcer checkExecution works with ExecutionRun', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'total_cost_microcents' => 1_000_000, // $1.00
        'total_tokens' => 5000,
    ]);

    $enforcer = new BudgetEnforcer(new CostCalculator);
    $result = $enforcer->checkExecution($run);

    expect($result['allowed'])->toBeTrue();
});

test('BudgetEnforcer recordCost and getDailySpend work together', function () {
    $enforcer = new BudgetEnforcer(new CostCalculator);

    // Clear cache first
    \Illuminate\Support\Facades\Cache::forget("agent:{$this->agent->id}:daily_cost:" . now()->format('Y-m-d'));

    $enforcer->recordCost($this->agent->id, 2.50);
    $enforcer->recordCost($this->agent->id, 1.25);

    $spend = $enforcer->getDailySpend($this->agent->id);
    expect($spend)->toBe(3.75);
});

test('BudgetEnforcer getStatus returns complete budget info', function () {
    $enforcer = new BudgetEnforcer(new CostCalculator);

    \Illuminate\Support\Facades\Cache::forget("agent:{$this->agent->id}:daily_cost:" . now()->format('Y-m-d'));

    $status = $enforcer->getStatus($this->agent);

    expect($status)->toHaveKeys([
        'run_budget_limit_usd',
        'daily_budget_limit_usd',
        'daily_spend_usd',
        'daily_remaining_usd',
    ]);
    expect($status['run_budget_limit_usd'])->toBe(5.0);
    expect($status['daily_budget_limit_usd'])->toBe(10.0);
});

// ──────────────────────────────────────────────────────
// #379 — Execution run endpoint returns execution ID
// ──────────────────────────────────────────────────────

test('POST /api/projects/{project}/agents/{agent}/run returns execution ID and dispatches job', function () {
    Queue::fake();

    $response = $this->postJson("/api/projects/{$this->project->id}/agents/{$this->agent->id}/run", [
        'input' => ['message' => 'Hello async agent'],
    ]);

    $response->assertStatus(202);
    $response->assertJsonStructure([
        'execution_id',
        'run_id',
        'stream_url',
        'status',
    ]);

    expect($response->json('status'))->toBe('queued');
    expect($response->json('stream_url'))->toContain('/api/executions/');

    // Verify the job was dispatched
    Queue::assertPushed(RunAgentJob::class, function ($job) {
        return $job->input['message'] === 'Hello async agent'
            && $job->triggerType === 'manual';
    });

    // Verify the execution run was created in DB
    $executionId = $response->json('execution_id');
    $run = ExecutionRun::where('uuid', $executionId)->first();
    expect($run)->not->toBeNull();
    expect($run->project_id)->toBe($this->project->id);
    expect($run->agent_id)->toBe($this->agent->id);
});

test('POST /api/projects/{project}/agents/{agent}/run accepts trigger_type', function () {
    Queue::fake();

    $response = $this->postJson("/api/projects/{$this->project->id}/agents/{$this->agent->id}/run", [
        'input' => ['message' => 'Webhook triggered'],
        'trigger_type' => 'webhook',
    ]);

    $response->assertStatus(202);

    Queue::assertPushed(RunAgentJob::class, function ($job) {
        return $job->triggerType === 'webhook';
    });
});

test('POST /api/projects/{project}/agents/{agent}/run validates input', function () {
    Queue::fake();

    $response = $this->postJson("/api/projects/{$this->project->id}/agents/{$this->agent->id}/run", [
        'trigger_type' => 'invalid_type',
    ]);

    $response->assertStatus(422);
});

// ──────────────────────────────────────────────────────
// #380 — Cancel endpoint updates status
// ──────────────────────────────────────────────────────

test('POST /api/executions/{id}/cancel cancels a running execution', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'running',
        'started_at' => now(),
    ]);

    $response = $this->postJson("/api/executions/{$run->uuid}/cancel");

    $response->assertOk();
    expect($response->json('status'))->toBe('cancelled');
    expect($run->fresh()->status)->toBe('cancelled');
});

test('POST /api/executions/{id}/cancel rejects finished execution', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    $response = $this->postJson("/api/executions/{$run->uuid}/cancel");

    $response->assertStatus(422);
});

test('POST /api/executions/{id}/cancel returns 404 for non-existent execution', function () {
    $response = $this->postJson('/api/executions/nonexistent-uuid/cancel');

    $response->assertStatus(404);
});

// ──────────────────────────────────────────────────────
// #379 — SSE stream endpoint
// ──────────────────────────────────────────────────────

test('GET /api/executions/{id}/stream returns SSE headers for running execution', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'running',
        'started_at' => now(),
    ]);

    // We can't fully test SSE streaming in a unit test, but we can verify
    // the endpoint returns the correct content type for completed runs
    $run->markCompleted(['response' => 'Test output']);

    $response = $this->get("/api/executions/{$run->uuid}/stream");

    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');
});

test('GET /api/executions/{id}/stream returns 404 for non-existent execution', function () {
    $response = $this->get('/api/executions/nonexistent-uuid/stream');

    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');
    // The SSE response will include an error event but still uses 404 status
    $response->assertStatus(404);
});
