<?php

use App\Models\Agent;
use App\Models\ExecutionRun;
use App\Models\Project;
use App\Models\Skill;
use App\Models\User;
use App\Services\AgentRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(AgentRoutingService::class);

    $this->user = User::firstOrCreate(
        ['email' => 'routing@test.com'],
        ['name' => 'Routing', 'password' => bcrypt('password')],
    );

    $this->project = Project::create([
        'name' => 'Route Project',
        'path' => '/tmp/route',
        'providers' => ['claude'],
        'owner_id' => $this->user->id,
    ]);

    $this->billingAgent = Agent::create([
        'name' => 'Billing Bot',
        'role' => 'finance',
        'base_instructions' => 'handle billing',
        'model' => 'claude-sonnet-4-6',
        'objective_template' => 'x',
        'success_criteria' => [],
        'max_iterations' => 5,
        'planning_mode' => 'plan_then_act',
        'loop_condition' => 'goal_met',
        'owner_user_id' => $this->user->id,
    ]);

    $this->searchAgent = Agent::create([
        'name' => 'Search Bot',
        'role' => 'research',
        'base_instructions' => 'do research',
        'model' => 'claude-sonnet-4-6',
        'objective_template' => 'x',
        'success_criteria' => [],
        'max_iterations' => 5,
        'planning_mode' => 'plan_then_act',
        'loop_condition' => 'goal_met',
        'owner_user_id' => $this->user->id,
    ]);

    $billingSkill = Skill::create([
        'project_id' => $this->project->id,
        'name' => 'Invoice Generation',
        'slug' => 'invoice-gen',
        'description' => 'Generates subscription invoices and refund notices.',
        'body' => '...',
    ]);
    $searchSkill = Skill::create([
        'project_id' => $this->project->id,
        'name' => 'Web Search',
        'slug' => 'web-search',
        'description' => 'Queries the web and summarizes results.',
        'body' => '...',
    ]);

    DB::table('agent_skill')->insert([
        ['project_id' => $this->project->id, 'agent_id' => $this->billingAgent->id, 'skill_id' => $billingSkill->id],
        ['project_id' => $this->project->id, 'agent_id' => $this->searchAgent->id, 'skill_id' => $searchSkill->id],
    ]);
});

it('ranks billing agent higher for billing questions', function () {
    $results = $this->service->rank('Who should I ask about an invoice refund?');

    expect($results)->not->toBeEmpty()
        ->and($results[0]['agent_id'])->toBe($this->billingAgent->id)
        ->and($results[0]['reasoning'])->toContain('skill overlap');
});

it('ranks search agent higher for research questions', function () {
    $results = $this->service->rank('Can someone summarize recent web research on this?');

    expect($results[0]['agent_id'])->toBe($this->searchAgent->id);
});

it('boosts by past-run overlap', function () {
    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->billingAgent->id,
        'input' => ['message' => 'Reconcile the quarterly statements for merchants'],
        'status' => 'completed',
    ]);

    $results = $this->service->rank('quarterly statements merchants');

    expect($results[0]['agent_id'])->toBe($this->billingAgent->id)
        ->and($results[0]['reasoning'])->toContain('past-run');
});

it('returns empty array when question has no meaningful tokens', function () {
    $results = $this->service->rank('the');

    expect($results)->toBeEmpty();
});

it('scopes by project when project_id is given', function () {
    $otherProject = Project::create([
        'name' => 'Other',
        'path' => '/tmp/other',
        'providers' => ['claude'],
        'owner_id' => $this->user->id,
    ]);

    DB::table('project_agent')->insert([
        'project_id' => $otherProject->id,
        'agent_id' => $this->billingAgent->id,
        'is_enabled' => true,
    ]);

    $results = $this->service->rank('invoice refund', $otherProject->id);

    expect($results[0]['agent_id'])->toBe($this->billingAgent->id);
    expect(count($results))->toBe(1);
});

it('POST /api/agents/route returns ranked matches', function () {
    $response = $this->actingAs($this->user)->postJson('/api/agents/route', [
        'question' => 'Who handles invoice refunds?',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.0.agent_id', $this->billingAgent->id);
});
