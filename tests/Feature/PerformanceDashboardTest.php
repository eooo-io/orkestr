<?php

use App\Models\Agent;
use App\Models\AgentSchedule;
use App\Models\ExecutionRun;
use App\Models\ExecutionStep;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create(['name' => 'Perf Test Project', 'path' => '/tmp/perf-test']);
    $this->agent = Agent::create([
        'name' => 'Perf Agent',
        'slug' => 'perf-agent',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-6',
        'base_instructions' => 'You are a test agent.',
    ]);

    $this->project->agents()->attach($this->agent->id, ['is_enabled' => true]);
});

// ──── #194: Performance Dashboard ────

test('GET /api/performance/overview returns correct structure and aggregations', function () {
    // Create some runs
    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
        'total_tokens' => 1000,
        'total_cost_microcents' => 500_000,
        'total_duration_ms' => 2000,
    ]);
    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'failed',
        'total_tokens' => 500,
        'total_cost_microcents' => 200_000,
        'total_duration_ms' => 1000,
    ]);
    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
        'total_tokens' => 800,
        'total_cost_microcents' => 400_000,
        'total_duration_ms' => 1500,
    ]);

    $response = $this->getJson('/api/performance/overview');

    $response->assertOk();
    $data = $response->json('data');

    expect($data['total_runs'])->toBe(3);
    expect($data['successful_runs'])->toBe(2);
    expect($data['failed_runs'])->toBe(1);
    expect($data['success_rate'])->toBe(66.7);
    expect($data['total_tokens'])->toBe(2300);
    expect($data['avg_duration_ms'])->toBeGreaterThan(0);
    expect($data['active_agents'])->toBe(1);
    expect($data)->toHaveKeys([
        'total_runs', 'successful_runs', 'failed_runs', 'success_rate',
        'total_cost_usd', 'avg_cost_per_run_usd', 'total_tokens',
        'avg_tokens_per_run', 'avg_duration_ms', 'p95_duration_ms', 'active_agents',
    ]);
});

test('GET /api/performance/overview filters by period', function () {
    // Recent run
    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
        'total_tokens' => 500,
        'total_cost_microcents' => 100_000,
    ]);

    // Old run (force old date)
    $oldRun = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
        'total_tokens' => 1000,
        'total_cost_microcents' => 200_000,
    ]);
    ExecutionRun::where('id', $oldRun->id)->update(['created_at' => now()->subDays(60)]);

    // 7d period should only show 1 run
    $response = $this->getJson('/api/performance/overview?period=7d');
    $response->assertOk();
    expect($response->json('data.total_runs'))->toBe(1);

    // 90d period should show both
    $response = $this->getJson('/api/performance/overview?period=90d');
    $response->assertOk();
    expect($response->json('data.total_runs'))->toBe(2);
});

test('GET /api/performance/overview filters by agent_id', function () {
    $agent2 = Agent::create([
        'name' => 'Agent 2',
        'slug' => 'agent-2',
        'role' => 'assistant',
        'base_instructions' => 'Test.',
    ]);

    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
    ]);
    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $agent2->id,
        'status' => 'completed',
    ]);

    $response = $this->getJson("/api/performance/overview?agent_id={$this->agent->id}");
    $response->assertOk();
    expect($response->json('data.total_runs'))->toBe(1);
});

test('GET /api/performance/agents returns per-agent performance comparison', function () {
    $agent2 = Agent::create([
        'name' => 'Agent Two',
        'slug' => 'agent-two',
        'role' => 'assistant',
        'base_instructions' => 'Test.',
    ]);

    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
        'total_cost_microcents' => 500_000,
        'total_duration_ms' => 2000,
    ]);
    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
        'total_cost_microcents' => 300_000,
        'total_duration_ms' => 1500,
    ]);
    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $agent2->id,
        'status' => 'failed',
        'total_cost_microcents' => 100_000,
        'total_duration_ms' => 500,
    ]);

    $response = $this->getJson('/api/performance/agents');
    $response->assertOk();

    $data = $response->json('data');
    expect($data)->toHaveCount(2);

    // Default sort is by run_count desc, so perf-agent first
    expect($data[0]['agent_name'])->toBe('Perf Agent');
    expect($data[0]['run_count'])->toBe(2);
    expect((float) $data[0]['success_rate'])->toBe(100.0);
    expect($data[0])->toHaveKeys([
        'agent_id', 'agent_name', 'run_count', 'success_rate',
        'avg_cost_usd', 'avg_duration_ms', 'total_cost_usd', 'last_run_at',
    ]);
});

test('GET /api/performance/trends returns daily aggregates', function () {
    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
        'total_tokens' => 1000,
        'total_cost_microcents' => 500_000,
    ]);
    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'failed',
        'total_tokens' => 400,
        'total_cost_microcents' => 200_000,
    ]);

    $response = $this->getJson('/api/performance/trends');
    $response->assertOk();

    $data = $response->json('data');
    expect($data)->toHaveCount(1); // Both runs are today

    $today = $data[0];
    expect($today['run_count'])->toBe(2);
    expect($today['success_count'])->toBe(1);
    expect($today['failure_count'])->toBe(1);
    expect($today['total_tokens'])->toBe(1400);
    expect($today)->toHaveKeys(['date', 'run_count', 'success_count', 'failure_count', 'total_cost_usd', 'total_tokens']);
});

test('GET /api/performance/models returns model usage breakdown', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
        'total_tokens' => 500,
        'total_cost_microcents' => 200_000,
    ]);

    ExecutionStep::create([
        'execution_run_id' => $run->id,
        'step_number' => 1,
        'phase' => 'reason',
        'model_used' => 'claude-sonnet-4-6',
        'duration_ms' => 1500,
        'status' => 'completed',
    ]);

    $response = $this->getJson('/api/performance/models');
    $response->assertOk();

    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['model_name'])->toBe('claude-sonnet-4-6');
    expect($data[0])->toHaveKeys(['model_name', 'run_count', 'total_tokens', 'total_cost_usd', 'avg_latency_ms']);
});

test('GET /api/performance/cost-breakdown by agent', function () {
    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
        'total_cost_microcents' => 500_000,
        'total_tokens' => 1000,
    ]);

    $response = $this->getJson('/api/performance/cost-breakdown?group_by=agent');
    $response->assertOk();

    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['group_name'])->toBe('Perf Agent');
    expect($data[0])->toHaveKeys(['group_id', 'group_name', 'run_count', 'total_cost_usd', 'total_tokens']);
});

test('GET /api/performance/cost-breakdown by model', function () {
    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
        'total_cost_microcents' => 500_000,
        'total_tokens' => 1000,
        'model_used' => 'claude-sonnet-4-6',
    ]);

    $response = $this->getJson('/api/performance/cost-breakdown?group_by=model');
    $response->assertOk();

    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['group_name'])->toBe('claude-sonnet-4-6');
});

test('GET /api/performance/cost-breakdown by project', function () {
    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
        'total_cost_microcents' => 500_000,
        'total_tokens' => 1000,
    ]);

    $response = $this->getJson('/api/performance/cost-breakdown?group_by=project');
    $response->assertOk();

    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['group_name'])->toBe('Perf Test Project');
});

// ──── #195: Agents Overview ────

test('GET /api/agents/overview returns dashboard data', function () {
    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
        'total_duration_ms' => 1500,
        'total_cost_microcents' => 300_000,
    ]);

    $response = $this->getJson('/api/agents/overview');
    $response->assertOk();

    $data = $response->json('data');
    expect($data['total_agents'])->toBeGreaterThanOrEqual(1);
    expect($data['active_agents'])->toBe(1);
    expect($data['total_runs_today'])->toBe(1);
    expect($data)->toHaveKeys([
        'total_agents', 'active_agents', 'total_runs_today',
        'total_cost_today', 'recent_runs', 'top_agents',
    ]);

    expect($data['recent_runs'])->toHaveCount(1);
    expect($data['recent_runs'][0]['agent_name'])->toBe('Perf Agent');
});

// ──── #197: Agent Team Overview ────

test('GET /api/projects/{project}/agent-team returns team overview', function () {
    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
        'total_cost_microcents' => 500_000,
    ]);

    $response = $this->getJson("/api/projects/{$this->project->id}/agent-team");
    $response->assertOk();

    $data = $response->json('data');
    expect($data)->toHaveCount(1);

    $agentData = $data[0];
    expect($agentData['name'])->toBe('Perf Agent');
    expect($agentData['model'])->toBe('claude-sonnet-4-6');
    expect($agentData['is_enabled'])->toBeTrue();
    expect($agentData)->toHaveKeys([
        'id', 'name', 'slug', 'icon', 'model', 'autonomy_level',
        'is_enabled', 'performance', 'schedule', 'status', 'workflows',
    ]);
    expect($agentData['performance']['run_count'])->toBe(1);
    expect((float) $agentData['performance']['success_rate'])->toBe(100.0);
});

test('GET /api/projects/{project}/agent-team includes schedule info', function () {
    AgentSchedule::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'name' => 'Daily Run',
        'trigger_type' => 'cron',
        'cron_expression' => '0 9 * * *',
        'is_enabled' => true,
        'next_run_at' => now()->addHour(),
    ]);

    $response = $this->getJson("/api/projects/{$this->project->id}/agent-team");
    $response->assertOk();

    $data = $response->json('data');
    expect($data[0]['schedule']['next_run_at'])->not->toBeNull();
});

// ──── #199: Onboarding ────

test('GET /api/onboarding/status returns onboarding progress', function () {
    $response = $this->getJson('/api/onboarding/status');
    $response->assertOk();

    $data = $response->json('data');
    expect($data)->toHaveKeys([
        'has_project', 'has_agent', 'has_skill', 'has_run',
        'has_schedule', 'completed_steps', 'total_steps',
    ]);
    expect($data['total_steps'])->toBe(5);
    expect($data['has_project'])->toBeTrue();  // Created in beforeEach
    expect($data['has_agent'])->toBeTrue();    // Created in beforeEach
    expect($data['has_skill'])->toBeFalse();
    expect($data['has_run'])->toBeFalse();
    expect($data['has_schedule'])->toBeFalse();
    expect($data['completed_steps'])->toBe(2);
});

test('POST /api/onboarding/quick-start creates starter project and agent', function () {
    $response = $this->postJson('/api/onboarding/quick-start');
    $response->assertStatus(201);

    $data = $response->json('data');
    expect($data)->toHaveKeys(['project_id', 'agent_id', 'project_name', 'agent_name']);
    expect($data['project_name'])->toBe('My First Project');

    // Verify project was created
    $this->assertDatabaseHas('projects', ['id' => $data['project_id'], 'name' => 'My First Project']);

    // Verify agent is attached to project
    $this->assertDatabaseHas('project_agent', [
        'project_id' => $data['project_id'],
        'agent_id' => $data['agent_id'],
        'is_enabled' => true,
    ]);
});

test('POST /api/onboarding/quick-start uses existing agent if available', function () {
    $existingAgentCount = Agent::count();

    $response = $this->postJson('/api/onboarding/quick-start');
    $response->assertStatus(201);

    // Should reuse the existing agent from beforeEach, not create a new one
    expect($response->json('data.agent_id'))->toBe($this->agent->id);
    expect(Agent::count())->toBe($existingAgentCount);
});

test('GET /api/performance/overview returns zeros when no runs', function () {
    $response = $this->getJson('/api/performance/overview');
    $response->assertOk();

    $data = $response->json('data');
    expect($data['total_runs'])->toBe(0);
    expect($data['successful_runs'])->toBe(0);
    expect($data['failed_runs'])->toBe(0);
    expect($data['success_rate'])->toBe(0);
    expect($data['total_cost_usd'])->toBe(0);
    expect($data['total_tokens'])->toBe(0);
});

test('GET /api/performance/overview filters by project_id', function () {
    $project2 = Project::create(['name' => 'Other Project', 'path' => '/tmp/other']);

    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
    ]);
    ExecutionRun::create([
        'project_id' => $project2->id,
        'agent_id' => $this->agent->id,
        'status' => 'completed',
    ]);

    $response = $this->getJson("/api/performance/overview?project_id={$this->project->id}");
    $response->assertOk();
    expect($response->json('data.total_runs'))->toBe(1);
});
