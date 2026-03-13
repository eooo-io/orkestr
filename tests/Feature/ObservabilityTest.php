<?php

use App\Models\Agent;
use App\Models\ExecutionRun;
use App\Models\ExecutionStep;
use App\Models\Project;
use App\Models\User;
use App\Services\Execution\CostCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create(['name' => 'Obs Test', 'path' => '/tmp/obs-test']);
    $this->agent = Agent::create([
        'name' => 'Obs Agent',
        'slug' => 'obs-agent',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-6',
        'base_instructions' => 'You are a test agent.',
    ]);

    $this->project->agents()->attach($this->agent->id, ['is_enabled' => true]);
});

// --- CostCalculator tests ---

test('CostCalculator calculates cost for known model', function () {
    $calculator = new CostCalculator;

    $cost = $calculator->calculate('claude-sonnet-4-6', [
        'input_tokens' => 1000,
        'output_tokens' => 500,
    ]);

    // Sonnet: input 30 microcents/token, output 150 microcents/token
    // Input: ceil(1000 * 30 / 10000) = ceil(3.0) = 3
    // Output: ceil(500 * 150 / 10000) = ceil(7.5) = 8
    expect($cost)->toBe(11);
});

test('CostCalculator calculates cost for opus model', function () {
    $calculator = new CostCalculator;

    $cost = $calculator->calculate('claude-opus-4-6', [
        'input_tokens' => 10000,
        'output_tokens' => 2000,
    ]);

    // Opus: input 150, output 750 microcents/token
    // Input: ceil(10000 * 150 / 10000) = 150
    // Output: ceil(2000 * 750 / 10000) = 150
    expect($cost)->toBe(300);
});

test('CostCalculator defaults to Sonnet pricing for unknown model', function () {
    $calculator = new CostCalculator;

    $cost = $calculator->calculate('unknown-model-xyz', [
        'input_tokens' => 1000,
        'output_tokens' => 500,
    ]);

    // Same as Sonnet pricing
    $sonnetCost = $calculator->calculate('claude-sonnet-4-6', [
        'input_tokens' => 1000,
        'output_tokens' => 500,
    ]);

    expect($cost)->toBe($sonnetCost);
});

test('CostCalculator handles zero tokens', function () {
    $calculator = new CostCalculator;

    $cost = $calculator->calculate('claude-sonnet-4-6', [
        'input_tokens' => 0,
        'output_tokens' => 0,
    ]);

    expect($cost)->toBe(0);
});

test('CostCalculator handles missing token keys', function () {
    $calculator = new CostCalculator;

    $cost = $calculator->calculate('claude-sonnet-4-6', []);

    expect($cost)->toBe(0);
});

test('CostCalculator formats cost as dollar amount', function () {
    // Less than a cent
    expect(CostCalculator::formatCost(500))->toBe('< $0.01');

    // Exactly one cent
    expect(CostCalculator::formatCost(10000))->toBe('$0.0100');

    // $1.50
    expect(CostCalculator::formatCost(1500000))->toBe('$1.5000');

    // Zero
    expect(CostCalculator::formatCost(0))->toBe('< $0.01');
});

test('CostCalculator aggregates stats across runs', function () {
    $calculator = new CostCalculator;

    $agent2 = Agent::create([
        'name' => 'Agent 2',
        'slug' => 'agent-2',
        'role' => 'assistant',
        'model' => 'claude-opus-4-6',
        'base_instructions' => 'Second agent.',
    ]);

    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
        'total_tokens' => 1000,
        'total_cost_microcents' => 50,
        'total_duration_ms' => 2000,
    ]);

    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $agent2->id,
        'status' => 'completed',
        'total_tokens' => 2000,
        'total_cost_microcents' => 200,
        'total_duration_ms' => 3000,
    ]);

    $runs = $this->project->executionRuns()->with('agent')->get();
    $stats = $calculator->aggregateStats($runs);

    expect($stats['total_runs'])->toBe(2);
    expect($stats['total_tokens'])->toBe(3000);
    expect($stats['total_cost_microcents'])->toBe(250);
    expect($stats['total_duration_ms'])->toBe(5000);
    expect($stats['by_model'])->toHaveCount(2);
    expect($stats['by_model']['claude-sonnet-4-6']['runs'])->toBe(1);
    expect($stats['by_model']['claude-opus-4-6']['runs'])->toBe(1);
});

// --- Stats API endpoint tests ---

test('stats endpoint returns aggregate data', function () {
    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
        'total_tokens' => 500,
        'total_cost_microcents' => 25,
        'total_duration_ms' => 1000,
    ]);

    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'failed',
        'total_tokens' => 200,
        'total_cost_microcents' => 10,
        'total_duration_ms' => 500,
    ]);

    $response = $this->getJson("/api/projects/{$this->project->id}/runs/stats");

    $response->assertOk();
    expect($response->json('total_runs'))->toBe(2);
    expect($response->json('total_tokens'))->toBe(700);
    expect($response->json('total_cost_microcents'))->toBe(35);
    expect($response->json('success_rate'))->toEqual(50.0);
    expect($response->json('completed_count'))->toBe(1);
    expect($response->json('failed_count'))->toBe(1);
});

test('stats endpoint returns zeros for empty project', function () {
    $response = $this->getJson("/api/projects/{$this->project->id}/runs/stats");

    $response->assertOk();
    expect($response->json('total_runs'))->toBe(0);
    expect($response->json('total_tokens'))->toBe(0);
    expect($response->json('success_rate'))->toBe(0);
});

// --- Execution trace tests (step-level detail) ---

test('execution steps record token usage per step', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
    ]);

    $step = ExecutionStep::create([
        'execution_run_id' => $run->id,
        'step_number' => 1,
        'phase' => 'reason',
        'token_usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        'duration_ms' => 250,
        'status' => 'completed',
    ]);

    expect($step->token_usage['input_tokens'])->toBe(100);
    expect($step->token_usage['output_tokens'])->toBe(50);
    expect($step->duration_ms)->toBe(250);
});

test('execution steps record tool calls', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
    ]);

    $toolCalls = [
        ['tool_name' => 'read_file', 'input' => ['path' => '/tmp/test.txt'], 'result' => 'contents'],
        ['tool_name' => 'list_dir', 'input' => ['path' => '/tmp'], 'result' => ['file1', 'file2']],
    ];

    $step = ExecutionStep::create([
        'execution_run_id' => $run->id,
        'step_number' => 1,
        'phase' => 'act',
        'tool_calls' => $toolCalls,
        'status' => 'completed',
    ]);

    expect($step->tool_calls)->toHaveCount(2);
    expect($step->tool_calls[0]['tool_name'])->toBe('read_file');
});

test('run show endpoint includes step-level trace data', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
        'total_tokens' => 300,
        'total_cost_microcents' => 15,
    ]);

    ExecutionStep::create([
        'execution_run_id' => $run->id,
        'step_number' => 1,
        'phase' => 'perceive',
        'input' => ['message' => 'Hello'],
        'status' => 'completed',
        'duration_ms' => 10,
    ]);

    ExecutionStep::create([
        'execution_run_id' => $run->id,
        'step_number' => 2,
        'phase' => 'reason',
        'token_usage' => ['input_tokens' => 200, 'output_tokens' => 100],
        'status' => 'completed',
        'duration_ms' => 500,
    ]);

    $response = $this->getJson("/api/runs/{$run->id}");

    $response->assertOk();
    $data = $response->json('data');

    expect($data['total_tokens'])->toBe(300);
    expect($data['total_cost_microcents'])->toBe(15);
    expect($data['steps'])->toHaveCount(2);
    expect($data['steps'][0]['phase'])->toBe('perceive');
    expect($data['steps'][1]['token_usage']['input_tokens'])->toBe(200);
});

test('ExecutionRunResource includes cost formatting fields', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
        'total_tokens' => 1500,
        'total_cost_microcents' => 75,
        'total_duration_ms' => 3000,
        'started_at' => now()->subSeconds(3),
        'completed_at' => now(),
    ]);

    $response = $this->getJson("/api/runs/{$run->id}");
    $data = $response->json('data');

    expect($data['total_tokens'])->toBe(1500);
    expect($data['total_cost_microcents'])->toBe(75);
    expect($data['total_duration_ms'])->toBe(3000);
    expect($data['started_at'])->not->toBeNull();
    expect($data['completed_at'])->not->toBeNull();
});
