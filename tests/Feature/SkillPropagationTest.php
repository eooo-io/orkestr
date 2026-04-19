<?php

use App\Models\Agent;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectAgent;
use App\Models\Skill;
use App\Models\SkillEvalRun;
use App\Models\SkillEvalSuite;
use App\Models\SkillPropagation;
use App\Models\User;
use App\Services\SkillPropagationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(SkillPropagationService::class);

    $this->user = User::firstOrCreate(
        ['email' => 'prop@test.com'],
        ['name' => 'Prop', 'password' => bcrypt('password')],
    );

    $this->org = Organization::create(['name' => 'Prop Org']);
    $this->user->current_organization_id = $this->org->id;
    $this->user->save();

    $this->sourceProject = Project::create([
        'name' => 'Source',
        'path' => '/tmp/source',
        'providers' => ['claude'],
        'owner_id' => $this->user->id,
        'organization_id' => $this->org->id,
    ]);

    $this->targetProject = Project::create([
        'name' => 'Target',
        'path' => '/tmp/target',
        'providers' => ['claude'],
        'owner_id' => $this->user->id,
        'organization_id' => $this->org->id,
    ]);

    $this->sourceSkill = Skill::create([
        'project_id' => $this->sourceProject->id,
        'name' => 'Invoice Helper',
        'slug' => 'invoice-helper',
        'body' => 'Generate invoices.',
        'model' => 'claude-sonnet-4-6',
    ]);

    $suite = SkillEvalSuite::create([
        'skill_id' => $this->sourceSkill->id,
        'name' => 'Main',
    ]);

    SkillEvalRun::create([
        'eval_suite_id' => $suite->id,
        'model' => 'claude-sonnet-4-6',
        'mode' => 'with_skill',
        'status' => 'completed',
        'overall_score' => 88,
        'completed_at' => now(),
    ]);
});

it('creates propagation suggestions for high-performing skills into sibling projects', function () {
    $agent = Agent::create([
        'name' => 'Target Agent',
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
    ProjectAgent::create([
        'project_id' => $this->targetProject->id,
        'agent_id' => $agent->id,
        'is_enabled' => true,
    ]);

    $created = $this->service->suggestPropagations();

    expect($created)->toBeGreaterThan(0);

    $propagation = SkillPropagation::first();
    expect($propagation)->not->toBeNull()
        ->and($propagation->target_project_id)->toBe($this->targetProject->id)
        ->and($propagation->status)->toBe(SkillPropagation::STATUS_SUGGESTED)
        ->and((float) $propagation->suggestion_score)->toBeGreaterThan(0.4);
});

it('does not suggest when the target project already has a skill with the same slug', function () {
    Skill::create([
        'project_id' => $this->targetProject->id,
        'name' => 'Invoice Helper',
        'slug' => 'invoice-helper',
        'body' => 'Existing.',
    ]);

    $created = $this->service->suggestPropagations();

    expect($created)->toBe(0);
});

it('accept clones the source skill into the target project with a unique slug', function () {
    $propagation = SkillPropagation::create([
        'source_skill_id' => $this->sourceSkill->id,
        'target_project_id' => $this->targetProject->id,
        'status' => SkillPropagation::STATUS_SUGGESTED,
        'suggestion_score' => 0.8,
        'suggested_at' => now(),
    ]);

    $newSkill = $this->service->accept($propagation);

    expect($newSkill->project_id)->toBe($this->targetProject->id)
        ->and($newSkill->slug)->toBe('invoice-helper')
        ->and($newSkill->body)->toBe('Generate invoices.')
        ->and($propagation->fresh()->status)->toBe(SkillPropagation::STATUS_ACCEPTED);
});

it('accept with body_override marks status as modified', function () {
    $propagation = SkillPropagation::create([
        'source_skill_id' => $this->sourceSkill->id,
        'target_project_id' => $this->targetProject->id,
        'status' => SkillPropagation::STATUS_SUGGESTED,
        'suggestion_score' => 0.8,
        'suggested_at' => now(),
    ]);

    $newSkill = $this->service->accept($propagation, 'Customized body for target context.');

    expect($newSkill->body)->toBe('Customized body for target context.')
        ->and($propagation->fresh()->status)->toBe(SkillPropagation::STATUS_MODIFIED);
});

it('lineage endpoint surfaces source project info', function () {
    $propagation = SkillPropagation::create([
        'source_skill_id' => $this->sourceSkill->id,
        'target_project_id' => $this->targetProject->id,
        'status' => SkillPropagation::STATUS_SUGGESTED,
        'suggestion_score' => 0.8,
        'suggested_at' => now(),
    ]);
    $newSkill = $this->service->accept($propagation);

    $response = $this->actingAs($this->user)
        ->getJson("/api/skills/{$newSkill->id}/lineage");

    $response->assertOk()
        ->assertJsonPath('data.source_project_name', 'Source')
        ->assertJsonPath('data.source_skill_slug', 'invoice-helper');
});

it('lineage endpoint returns null when skill was not propagated', function () {
    $standalone = Skill::create([
        'project_id' => $this->targetProject->id,
        'name' => 'Native',
        'slug' => 'native',
        'body' => 'x',
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/skills/{$standalone->id}/lineage")
        ->assertOk()
        ->assertJsonPath('data', null);
});

it('propagation service skips incompatible model families', function () {
    $gptSkill = Skill::create([
        'project_id' => $this->sourceProject->id,
        'name' => 'GPT Helper',
        'slug' => 'gpt-helper',
        'body' => 'GPT flavored.',
        'model' => 'gpt-5.4',
        'tuned_for_model' => 'gpt-5.4',
    ]);
    $suite = SkillEvalSuite::create(['skill_id' => $gptSkill->id, 'name' => 'S']);
    SkillEvalRun::create([
        'eval_suite_id' => $suite->id,
        'model' => 'gpt-5.4',
        'mode' => 'with_skill',
        'status' => 'completed',
        'overall_score' => 90,
        'completed_at' => now(),
    ]);

    // Target only has a claude agent
    $claudeAgent = Agent::create([
        'name' => 'Claudey',
        'role' => 'worker',
        'base_instructions' => 'hi',
        'model' => 'claude-sonnet-4-6',
        'objective_template' => 'x',
        'success_criteria' => [],
        'max_iterations' => 5,
        'planning_mode' => 'plan_then_act',
        'loop_condition' => 'goal_met',
    ]);
    ProjectAgent::create([
        'project_id' => $this->targetProject->id,
        'agent_id' => $claudeAgent->id,
        'is_enabled' => true,
    ]);

    $this->service->suggestPropagations();

    $gptPropagation = SkillPropagation::where('source_skill_id', $gptSkill->id)->first();
    expect($gptPropagation)->toBeNull();
});
