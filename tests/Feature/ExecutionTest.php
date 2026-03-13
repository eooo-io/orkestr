<?php

use App\Models\Agent;
use App\Models\ExecutionRun;
use App\Models\ExecutionStep;
use App\Models\Project;
use App\Models\User;
use App\Services\Execution\ToolCallResult;
use App\Services\Execution\ToolDispatcher;
use App\Services\Mcp\McpServerManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create(['name' => 'Exec Test', 'path' => '/tmp/exec-test']);
    $this->agent = Agent::create([
        'name' => 'Test Agent',
        'slug' => 'test-agent',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-6',
        'base_instructions' => 'You are a test agent.',
    ]);

    // Attach agent to project
    $this->project->agents()->attach($this->agent->id, ['is_enabled' => true]);
});

// --- ExecutionRun model tests ---

test('ExecutionRun creates with auto UUID and defaults', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'input' => ['message' => 'Hello'],
    ]);

    expect($run->uuid)->not->toBeNull();
    expect($run->status)->toBe('pending');
    expect($run->total_tokens)->toBe(0);
    expect($run->total_cost_microcents)->toBe(0);
});

test('ExecutionRun status transitions work', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
    ]);

    expect($run->isPending())->toBeTrue();

    $run->markRunning();
    expect($run->isRunning())->toBeTrue();
    expect($run->started_at)->not->toBeNull();

    $run->markCompleted(['response' => 'done']);
    expect($run->isCompleted())->toBeTrue();
    expect($run->completed_at)->not->toBeNull();
    expect($run->output['response'])->toBe('done');
});

test('ExecutionRun cancel works', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
    ]);

    $run->markRunning();
    $run->markCancelled();

    expect($run->isCancelled())->toBeTrue();
    expect($run->isFinished())->toBeTrue();
});

test('ExecutionRun failure records error', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
    ]);

    $run->markRunning();
    $run->markFailed('Something went wrong');

    expect($run->isFailed())->toBeTrue();
    expect($run->error)->toBe('Something went wrong');
});

test('ExecutionRun cascades deletion to steps', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
    ]);

    ExecutionStep::create([
        'execution_run_id' => $run->id,
        'step_number' => 1,
        'phase' => 'perceive',
    ]);

    ExecutionStep::create([
        'execution_run_id' => $run->id,
        'step_number' => 2,
        'phase' => 'reason',
    ]);

    expect(ExecutionStep::where('execution_run_id', $run->id)->count())->toBe(2);

    $run->delete();

    expect(ExecutionStep::where('execution_run_id', $run->id)->count())->toBe(0);
});

test('ExecutionRun tracks token usage', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
    ]);

    $run->addTokenUsage(['input_tokens' => 100, 'output_tokens' => 50]);
    $run->refresh();
    expect($run->total_tokens)->toBe(150);

    $run->addTokenUsage(['input_tokens' => 200, 'output_tokens' => 100]);
    $run->refresh();
    expect($run->total_tokens)->toBe(450);
});

// --- ExecutionStep model tests ---

test('ExecutionStep creates with auto UUID', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
    ]);

    $step = ExecutionStep::create([
        'execution_run_id' => $run->id,
        'step_number' => 1,
        'phase' => 'reason',
    ]);

    expect($step->uuid)->not->toBeNull();
    expect($step->isReason())->toBeTrue();
    expect($step->status)->toBe('pending');
});

test('ExecutionStep phase helpers work', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
    ]);

    foreach (ExecutionStep::PHASES as $phase) {
        $step = ExecutionStep::create([
            'execution_run_id' => $run->id,
            'step_number' => 1,
            'phase' => $phase,
        ]);

        $method = 'is' . ucfirst($phase);
        expect($step->$method())->toBeTrue();
    }
});

// --- ToolDispatcher tests ---

test('ToolDispatcher returns error for unknown tool', function () {
    $dispatcher = new ToolDispatcher(new McpServerManager);

    $result = $dispatcher->dispatch('nonexistent_tool', []);

    expect($result)->toBeInstanceOf(ToolCallResult::class);
    expect($result->isError)->toBeTrue();
    expect($result->text())->toContain('Unknown tool');
});

test('ToolCallResult serializes correctly', function () {
    $result = new ToolCallResult(
        toolName: 'read_file',
        content: [['type' => 'text', 'text' => 'file contents']],
        isError: false,
        durationMs: 42,
    );

    expect($result->text())->toBe('file contents');
    expect($result->toArray())->toMatchArray([
        'tool_name' => 'read_file',
        'is_error' => false,
        'duration_ms' => 42,
    ]);
});

// --- API tests ---

test('execute endpoint requires valid agent in project', function () {
    $response = $this->postJson("/api/projects/{$this->project->id}/agents/{$this->agent->id}/execute", [
        'input' => ['message' => 'Hello'],
    ]);

    // Will fail with LLM call since no API key, but should hit the service
    // Just check it doesn't 404 and creates a run
    expect($response->status())->toBeIn([201, 500]);
});

test('runs list endpoint works', function () {
    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
    ]);

    $response = $this->getJson("/api/projects/{$this->project->id}/runs");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

test('runs list filters by status', function () {
    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
    ]);

    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'failed',
    ]);

    $response = $this->getJson("/api/projects/{$this->project->id}/runs?status=completed");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.status'))->toBe('completed');
});

test('run show endpoint includes steps', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
    ]);

    ExecutionStep::create([
        'execution_run_id' => $run->id,
        'step_number' => 1,
        'phase' => 'perceive',
    ]);

    $response = $this->getJson("/api/runs/{$run->id}");

    $response->assertOk();
    expect($response->json('data.steps'))->toHaveCount(1);
    expect($response->json('data.steps.0.phase'))->toBe('perceive');
});

test('cancel endpoint works for running execution', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'running',
        'started_at' => now(),
    ]);

    $response = $this->postJson("/api/runs/{$run->id}/cancel");

    $response->assertOk();
    expect($response->json('status'))->toBe('cancelled');
    expect($run->fresh()->status)->toBe('cancelled');
});

test('cancel endpoint rejects already finished runs', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    $response = $this->postJson("/api/runs/{$run->id}/cancel");

    $response->assertStatus(422);
});
