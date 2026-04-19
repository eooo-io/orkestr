<?php

use App\Jobs\RecomputeAgentReputationJob;
use App\Models\Agent;
use App\Models\ExecutionRun;
use App\Models\Project;
use App\Models\User;
use App\Services\AgentReputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(AgentReputationService::class);

    $this->user = User::firstOrCreate(
        ['email' => 'rep@test.com'],
        ['name' => 'Rep Tester', 'password' => bcrypt('password')],
    );

    $this->project = Project::create([
        'name' => 'Rep Project',
        'path' => '/tmp/rep',
        'providers' => ['claude'],
        'owner_id' => $this->user->id,
    ]);

    $this->agent = Agent::create([
        'name' => 'Repper',
        'role' => 'worker',
        'base_instructions' => 'hi',
        'model' => 'claude-sonnet-4-6',
        'objective_template' => 'x',
        'success_criteria' => [],
        'max_iterations' => 5,
        'planning_mode' => 'plan_then_act',
        'loop_condition' => 'goal_met',
        'owner_user_id' => $this->user->id,
        'created_by' => $this->user->id,
    ]);
});

function seedRun(int $agentId, int $projectId, string $status): ExecutionRun
{
    return ExecutionRun::create([
        'project_id' => $projectId,
        'agent_id' => $agentId,
        'input' => [],
        'status' => $status,
    ]);
}

it('returns 0 when agent has fewer than MIN_RUNS_FOR_SCORE runs', function () {
    seedRun($this->agent->id, $this->project->id, 'completed');
    seedRun($this->agent->id, $this->project->id, 'completed');

    expect($this->service->calculate($this->agent))->toBe(0.0);
});

it('boosts score for a high success rate', function () {
    for ($i = 0; $i < 10; $i++) {
        seedRun($this->agent->id, $this->project->id, 'completed');
    }

    $score = $this->service->calculate($this->agent);

    expect($score)->toBeGreaterThan(75.0);
});

it('penalizes halted_guardrail rate', function () {
    for ($i = 0; $i < 8; $i++) {
        seedRun($this->agent->id, $this->project->id, 'completed');
    }
    for ($i = 0; $i < 2; $i++) {
        seedRun($this->agent->id, $this->project->id, 'halted_guardrail');
    }

    $allGood = 50.0 + 30.0; // baseline for all-completed
    $withHalts = $this->service->calculate($this->agent);

    expect($withHalts)->toBeLessThan($allGood);
});

it('penalizes plain failures differently than halts', function () {
    for ($i = 0; $i < 7; $i++) {
        seedRun($this->agent->id, $this->project->id, 'completed');
    }
    for ($i = 0; $i < 3; $i++) {
        seedRun($this->agent->id, $this->project->id, 'failed');
    }

    $score = $this->service->calculate($this->agent);

    expect($score)->toBeLessThan(80.0)
        ->and($score)->toBeGreaterThan(40.0);
});

it('persists the score and timestamp via computeFor', function () {
    for ($i = 0; $i < 5; $i++) {
        seedRun($this->agent->id, $this->project->id, 'completed');
    }

    $this->service->computeFor($this->agent);
    $fresh = $this->agent->fresh();

    expect((float) $fresh->reputation_score)->toBeGreaterThan(0.0)
        ->and($fresh->reputation_last_computed_at)->not->toBeNull();
});

it('RecomputeAgentReputationJob iterates all agents', function () {
    $otherAgent = Agent::create([
        'name' => 'Other',
        'role' => 'worker',
        'base_instructions' => 'hi',
        'model' => 'claude-sonnet-4-6',
        'objective_template' => 'x',
        'success_criteria' => [],
        'max_iterations' => 5,
        'planning_mode' => 'plan_then_act',
        'loop_condition' => 'goal_met',
        'owner_user_id' => $this->user->id,
    ]);

    for ($i = 0; $i < 5; $i++) {
        seedRun($this->agent->id, $this->project->id, 'completed');
    }

    (new RecomputeAgentReputationJob())->handle($this->service);

    expect($this->agent->fresh()->reputation_last_computed_at)->not->toBeNull()
        ->and($otherAgent->fresh()->reputation_last_computed_at)->not->toBeNull();
});

it('GET /api/agents/{id}/profile returns owner + domain + recent runs', function () {
    seedRun($this->agent->id, $this->project->id, 'completed');

    $response = $this->actingAs($this->user)
        ->getJson("/api/agents/{$this->agent->id}/profile");

    $response->assertOk()
        ->assertJsonPath('data.name', 'Repper')
        ->assertJsonPath('data.owner.email', 'rep@test.com')
        ->assertJsonPath('data.total_invocations', 1);
});
