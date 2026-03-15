<?php

use App\Models\Agent;
use App\Models\ExecutionReplay;
use App\Models\ExecutionReplayStep;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create(['name' => 'Replay Test', 'path' => '/tmp/replay-test']);
    $this->agent = Agent::create([
        'name' => 'Test Agent',
        'slug' => 'test-agent',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-6',
        'base_instructions' => 'You are a test agent.',
    ]);
});

test('list execution replays returns paginated results', function () {
    ExecutionReplay::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Replay 1',
        'status' => 'completed',
        'total_steps' => 3,
        'total_tokens' => 500,
        'total_cost_microcents' => 1200,
        'total_duration_ms' => 3000,
        'started_at' => now()->subMinutes(5),
        'completed_at' => now(),
    ]);

    ExecutionReplay::create([
        'project_id' => $this->project->id,
        'name' => 'Replay 2',
        'status' => 'failed',
        'total_steps' => 1,
        'total_tokens' => 100,
    ]);

    $response = $this->getJson('/api/executions');

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
});

test('list execution replays can filter by project_id', function () {
    $otherProject = Project::create(['name' => 'Other', 'path' => '/tmp/other']);

    ExecutionReplay::create([
        'project_id' => $this->project->id,
        'name' => 'Mine',
        'status' => 'completed',
    ]);

    ExecutionReplay::create([
        'project_id' => $otherProject->id,
        'name' => 'Other',
        'status' => 'completed',
    ]);

    $response = $this->getJson("/api/executions?project_id={$this->project->id}");

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.name', 'Mine');
});

test('list execution replays can filter by agent_id', function () {
    $otherAgent = Agent::create([
        'name' => 'Other Agent',
        'slug' => 'other-agent',
        'role' => 'assistant',
        'base_instructions' => 'Test',
    ]);

    ExecutionReplay::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Agent 1 replay',
        'status' => 'completed',
    ]);

    ExecutionReplay::create([
        'project_id' => $this->project->id,
        'agent_id' => $otherAgent->id,
        'name' => 'Agent 2 replay',
        'status' => 'completed',
    ]);

    $response = $this->getJson("/api/executions?agent_id={$this->agent->id}");

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.name', 'Agent 1 replay');
});

test('show execution replay with steps', function () {
    $replay = ExecutionReplay::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Full Replay',
        'status' => 'completed',
        'total_steps' => 2,
        'total_tokens' => 800,
        'total_cost_microcents' => 2000,
        'total_duration_ms' => 5000,
    ]);

    ExecutionReplayStep::create([
        'execution_replay_id' => $replay->id,
        'step_number' => 1,
        'type' => 'llm_response',
        'input' => ['message' => 'Hello'],
        'output' => ['text' => 'Hi there'],
        'model' => 'claude-sonnet-4-6',
        'tokens_used' => 500,
        'cost_microcents' => 1200,
        'duration_ms' => 2000,
        'created_at' => now(),
    ]);

    ExecutionReplayStep::create([
        'execution_replay_id' => $replay->id,
        'step_number' => 2,
        'type' => 'tool_call',
        'input' => ['tool' => 'search'],
        'output' => ['result' => 'found'],
        'tokens_used' => 300,
        'cost_microcents' => 800,
        'duration_ms' => 3000,
        'created_at' => now(),
    ]);

    $response = $this->getJson("/api/executions/{$replay->id}");

    $response->assertOk();
    $response->assertJsonPath('data.name', 'Full Replay');
    $response->assertJsonPath('data.status', 'completed');
    $response->assertJsonCount(2, 'data.steps');
    $response->assertJsonPath('data.steps.0.type', 'llm_response');
    $response->assertJsonPath('data.steps.1.type', 'tool_call');
});

test('get execution steps endpoint returns ordered steps', function () {
    $replay = ExecutionReplay::create([
        'project_id' => $this->project->id,
        'name' => 'Steps Test',
        'status' => 'completed',
    ]);

    ExecutionReplayStep::create([
        'execution_replay_id' => $replay->id,
        'step_number' => 2,
        'type' => 'observation',
        'created_at' => now(),
    ]);

    ExecutionReplayStep::create([
        'execution_replay_id' => $replay->id,
        'step_number' => 1,
        'type' => 'llm_response',
        'created_at' => now(),
    ]);

    $response = $this->getJson("/api/executions/{$replay->id}/steps");

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
    $response->assertJsonPath('data.0.step_number', 1);
    $response->assertJsonPath('data.1.step_number', 2);
});

test('diff endpoint returns aligned steps and summary', function () {
    $replayA = ExecutionReplay::create([
        'project_id' => $this->project->id,
        'name' => 'Replay A',
        'status' => 'completed',
        'total_steps' => 2,
        'total_tokens' => 500,
        'total_cost_microcents' => 1000,
        'total_duration_ms' => 3000,
    ]);

    ExecutionReplayStep::create([
        'execution_replay_id' => $replayA->id,
        'step_number' => 1,
        'type' => 'llm_response',
        'tokens_used' => 300,
        'created_at' => now(),
    ]);

    ExecutionReplayStep::create([
        'execution_replay_id' => $replayA->id,
        'step_number' => 2,
        'type' => 'tool_call',
        'tokens_used' => 200,
        'created_at' => now(),
    ]);

    $replayB = ExecutionReplay::create([
        'project_id' => $this->project->id,
        'name' => 'Replay B',
        'status' => 'completed',
        'total_steps' => 3,
        'total_tokens' => 900,
        'total_cost_microcents' => 2500,
        'total_duration_ms' => 6000,
    ]);

    ExecutionReplayStep::create([
        'execution_replay_id' => $replayB->id,
        'step_number' => 1,
        'type' => 'llm_response',
        'tokens_used' => 400,
        'created_at' => now(),
    ]);

    ExecutionReplayStep::create([
        'execution_replay_id' => $replayB->id,
        'step_number' => 2,
        'type' => 'decision',
        'tokens_used' => 200,
        'created_at' => now(),
    ]);

    ExecutionReplayStep::create([
        'execution_replay_id' => $replayB->id,
        'step_number' => 3,
        'type' => 'tool_call',
        'tokens_used' => 300,
        'created_at' => now(),
    ]);

    $response = $this->getJson("/api/executions/{$replayA->id}/diff/{$replayB->id}");

    $response->assertOk();

    // Should have 3 aligned entries (max of both)
    $response->assertJsonCount(3, 'data.left');
    $response->assertJsonCount(3, 'data.right');

    // Summary diffs
    $response->assertJsonPath('data.summary.tokens_diff', 400);    // 900 - 500
    $response->assertJsonPath('data.summary.cost_diff', 1500);     // 2500 - 1000
    $response->assertJsonPath('data.summary.duration_diff', 3000); // 6000 - 3000
    $response->assertJsonPath('data.summary.steps_diff', 1);       // 3 - 2

    // Left step 3 should be null (replay A only had 2 steps)
    $response->assertJsonPath('data.left.2', null);
});

test('list execution replays can filter by status', function () {
    ExecutionReplay::create([
        'project_id' => $this->project->id,
        'name' => 'Completed',
        'status' => 'completed',
    ]);

    ExecutionReplay::create([
        'project_id' => $this->project->id,
        'name' => 'Failed',
        'status' => 'failed',
    ]);

    $response = $this->getJson('/api/executions?status=completed');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.name', 'Completed');
});
