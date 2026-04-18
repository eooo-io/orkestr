<?php

use App\Models\Agent;
use App\Models\ComposeShareLink;
use App\Models\Project;
use App\Models\ProjectAgent;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::firstOrCreate(
        ['email' => 'share@test.com'],
        ['name' => 'Share Tester', 'password' => bcrypt('password')],
    );

    $this->project = Project::create([
        'name' => 'Share Project',
        'path' => '/tmp/share-project',
        'providers' => ['claude'],
        'owner_id' => $this->user->id,
    ]);

    $this->agent = Agent::create([
        'name' => 'Share Agent',
        'role' => 'reviewer',
        'base_instructions' => 'You are a reviewer.',
        'model' => 'claude-sonnet-4-6',
        'objective_template' => 'Review code',
        'success_criteria' => ['reviewed'],
        'max_iterations' => 5,
        'planning_mode' => 'plan_then_act',
        'loop_condition' => 'goal_met',
    ]);

    ProjectAgent::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'is_enabled' => true,
    ]);
});

it('creates a snapshot share link for a clean agent', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/agents/{$this->agent->id}/compose/share", [
            'depth' => 'full',
            'is_snapshot' => true,
            'expires_in_days' => 3,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.is_snapshot', true);

    $link = ComposeShareLink::where('created_by', $this->user->id)->first();
    expect($link)->not->toBeNull()
        ->and($link->snapshot_payload)->not->toBeNull()
        ->and($link->expires_at)->not->toBeNull()
        ->and($link->uuid)->toBeString();
});

it('refuses to create a share link when composed output contains a secret', function () {
    $skill = Skill::create([
        'project_id' => $this->project->id,
        'name' => 'Leaky Skill',
        'slug' => 'leaky',
        'body' => 'API key sk-ant-abc1234567890abcdefghij for access.',
    ]);

    DB::table('agent_skill')->insert([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'skill_id' => $skill->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/agents/{$this->agent->id}/compose/share", []);

    $response->assertStatus(422)
        ->assertJsonStructure(['error', 'secrets']);

    expect(ComposeShareLink::count())->toBe(0);
});

it('serves a snapshot payload and increments access_count', function () {
    $link = ComposeShareLink::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'depth' => 'full',
        'created_by' => $this->user->id,
        'expires_at' => now()->addDays(7),
        'is_snapshot' => true,
        'snapshot_payload' => [
            'content' => "# Snapshot content\n",
            'token_estimate' => 5,
            'skill_count' => 0,
            'target_model' => 'claude-sonnet-4-6',
            'model_context_window' => 200000,
            'skill_breakdown' => [],
            'agent' => ['name' => 'Share Agent'],
        ],
    ]);

    $this->getJson("/api/share/compose/{$link->uuid}")
        ->assertOk()
        ->assertJsonPath('data.content', "# Snapshot content\n")
        ->assertJsonPath('data.target_model', 'claude-sonnet-4-6');

    expect($link->fresh()->access_count)->toBe(1)
        ->and($link->fresh()->last_accessed_at)->not->toBeNull();
});

it('returns 410 for expired share links', function () {
    $link = ComposeShareLink::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'depth' => 'full',
        'created_by' => $this->user->id,
        'expires_at' => now()->subDay(),
        'is_snapshot' => true,
        'snapshot_payload' => ['content' => ''],
    ]);

    $this->getJson("/api/share/compose/{$link->uuid}")->assertStatus(410);
});

it('returns 404 for missing share links', function () {
    $this->getJson('/api/share/compose/does-not-exist')->assertNotFound();
});

it('prevents non-creators from deleting share links', function () {
    $link = ComposeShareLink::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'depth' => 'full',
        'created_by' => $this->user->id,
        'expires_at' => now()->addDays(7),
        'is_snapshot' => true,
        'snapshot_payload' => ['content' => ''],
    ]);

    $other = User::create([
        'email' => 'other@test.com',
        'name' => 'Other',
        'password' => bcrypt('x'),
    ]);

    $this->actingAs($other)
        ->deleteJson("/api/share/compose/{$link->uuid}")
        ->assertForbidden();
});
