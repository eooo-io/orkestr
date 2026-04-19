<?php

use App\Models\Agent;
use App\Models\AgentMemory;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SkillUpdateProposal;
use App\Models\User;
use App\Services\PatternExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(PatternExtractionService::class);

    $this->user = User::firstOrCreate(
        ['email' => 'pattern@test.com'],
        ['name' => 'Pattern', 'password' => bcrypt('password')],
    );

    $this->project = Project::create([
        'name' => 'Pattern Project',
        'path' => '/tmp/pattern',
        'providers' => ['claude'],
        'owner_id' => $this->user->id,
    ]);

    $this->agent = Agent::create([
        'name' => 'Patterner',
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
});

function mem(int $agentId, int $projectId, string $text, string $type = 'long_term'): AgentMemory
{
    return AgentMemory::create([
        'uuid' => (string) Str::uuid(),
        'agent_id' => $agentId,
        'project_id' => $projectId,
        'type' => $type,
        'content' => ['text' => $text],
    ]);
}

it('opens a proposal when the same canonical text appears past the threshold', function () {
    mem($this->agent->id, $this->project->id, 'Always use pnpm for this project');
    mem($this->agent->id, $this->project->id, 'always use pnpm for this project!');
    mem($this->agent->id, $this->project->id, 'Please use pnpm for this project.');

    $created = $this->service->extractForAgent($this->agent);

    expect($created)->toBe(1);
    expect(SkillUpdateProposal::count())->toBe(1);

    $proposal = SkillUpdateProposal::first();
    expect($proposal->status)->toBe(SkillUpdateProposal::STATUS_DRAFT)
        ->and($proposal->evidence_memory_ids)->toHaveCount(3);
});

it('does not open a proposal below threshold', function () {
    mem($this->agent->id, $this->project->id, 'Always use pnpm');
    mem($this->agent->id, $this->project->id, 'use pnpm please');

    $created = $this->service->extractForAgent($this->agent);

    expect($created)->toBe(0);
    expect(SkillUpdateProposal::count())->toBe(0);
});

it('does not reopen a recently rejected suppressed proposal', function () {
    mem($this->agent->id, $this->project->id, 'Always use pnpm here');
    mem($this->agent->id, $this->project->id, 'Use pnpm here');
    mem($this->agent->id, $this->project->id, 'use pnpm here');

    $first = $this->service->extractForAgent($this->agent);
    expect($first)->toBe(1);

    $proposal = SkillUpdateProposal::first();
    $proposal->update([
        'status' => SkillUpdateProposal::STATUS_REJECTED,
        'suppress_until' => now()->addDays(30),
    ]);

    $second = $this->service->extractForAgent($this->agent);
    expect($second)->toBe(0);

    $fresh = SkillUpdateProposal::find($proposal->id);
    expect($fresh->status)->toBe(SkillUpdateProposal::STATUS_REJECTED);
});

it('accept endpoint creates a new skill_version and updates the skill', function () {
    $skill = Skill::create([
        'project_id' => $this->project->id,
        'name' => 'Dev Setup',
        'slug' => 'dev-setup',
        'body' => 'Old body.',
    ]);

    $proposal = SkillUpdateProposal::create([
        'skill_id' => $skill->id,
        'agent_id' => $this->agent->id,
        'title' => 'Encode repeated feedback: use pnpm',
        'proposed_body' => 'Always use pnpm for dependency management.',
        'evidence_memory_ids' => [1, 2, 3],
        'pattern_key' => 'pnpm dependency management',
        'status' => SkillUpdateProposal::STATUS_DRAFT,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/skill-proposals/{$proposal->id}/accept");

    $response->assertOk();

    $fresh = $skill->fresh();
    expect($fresh->body)->toBe('Always use pnpm for dependency management.')
        ->and($fresh->versions()->count())->toBe(1)
        ->and($fresh->versions()->first()->note)->toContain('Accepted proposal');

    expect($proposal->fresh()->status)->toBe(SkillUpdateProposal::STATUS_ACCEPTED);
});

it('reject endpoint suppresses with 30-day default', function () {
    $proposal = SkillUpdateProposal::create([
        'agent_id' => $this->agent->id,
        'title' => 'x',
        'proposed_body' => 'y',
        'pattern_key' => 'key',
        'status' => SkillUpdateProposal::STATUS_DRAFT,
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/skill-proposals/{$proposal->id}/reject")
        ->assertOk();

    $fresh = $proposal->fresh();
    expect($fresh->status)->toBe(SkillUpdateProposal::STATUS_REJECTED)
        ->and($fresh->suppress_until)->not->toBeNull();
});

it('accept refuses a new-skill proposal without skill_id', function () {
    $proposal = SkillUpdateProposal::create([
        'skill_id' => null,
        'agent_id' => $this->agent->id,
        'title' => 'x',
        'proposed_body' => 'y',
        'pattern_key' => 'key',
        'status' => SkillUpdateProposal::STATUS_DRAFT,
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/skill-proposals/{$proposal->id}/accept")
        ->assertStatus(422);
});

it('inbox scopes proposals to agents owned by the current user', function () {
    $other = User::create([
        'email' => 'other-owner@test.com',
        'name' => 'Other',
        'password' => bcrypt('x'),
    ]);
    $otherAgent = Agent::create([
        'name' => 'NotMine',
        'role' => 'worker',
        'base_instructions' => 'hi',
        'model' => 'claude-sonnet-4-6',
        'objective_template' => 'x',
        'success_criteria' => [],
        'max_iterations' => 5,
        'planning_mode' => 'plan_then_act',
        'loop_condition' => 'goal_met',
        'owner_user_id' => $other->id,
    ]);

    SkillUpdateProposal::create([
        'agent_id' => $this->agent->id,
        'title' => 'mine',
        'pattern_key' => 'a',
        'status' => SkillUpdateProposal::STATUS_DRAFT,
    ]);
    SkillUpdateProposal::create([
        'agent_id' => $otherAgent->id,
        'title' => 'theirs',
        'pattern_key' => 'b',
        'status' => SkillUpdateProposal::STATUS_DRAFT,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/skill-proposals');

    $titles = collect($response->json('data'))->pluck('title')->all();
    expect($titles)->toBe(['mine']);
});
