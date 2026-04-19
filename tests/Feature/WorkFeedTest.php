<?php

use App\Models\Agent;
use App\Models\ExecutionRun;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::firstOrCreate(
        ['email' => 'feed@test.com'],
        ['name' => 'Feed', 'password' => bcrypt('password')],
    );

    $this->other = User::firstOrCreate(
        ['email' => 'feed-other@test.com'],
        ['name' => 'Other', 'password' => bcrypt('password')],
    );

    $this->org = Organization::create(['name' => 'Feed Org']);
    $this->user->current_organization_id = $this->org->id;
    $this->user->save();

    $this->project = Project::create([
        'name' => 'Feed Project',
        'path' => '/tmp/feed',
        'providers' => ['claude'],
        'owner_id' => $this->user->id,
        'organization_id' => $this->org->id,
    ]);

    $this->agent = Agent::create([
        'name' => 'Feeder',
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

function run(array $overrides = []): ExecutionRun
{
    return ExecutionRun::create(array_merge([
        'project_id' => test()->project->id,
        'agent_id' => test()->agent->id,
        'input' => ['message' => 'Test work'],
        'status' => 'completed',
        'visibility' => 'org',
        'created_by' => test()->user->id,
    ], $overrides));
}

it('work feed only surfaces team + org runs in current org', function () {
    $orgRun = run(['visibility' => 'org']);
    $teamRun = run(['visibility' => 'team']);
    run(['visibility' => 'private']); // should be excluded

    // Run in a different org — should be excluded
    $otherOrg = Organization::create(['name' => 'Other Org']);
    $otherProject = Project::create([
        'name' => 'Other', 'path' => '/tmp/other', 'providers' => ['claude'],
        'owner_id' => $this->other->id, 'organization_id' => $otherOrg->id,
    ]);
    ExecutionRun::create([
        'project_id' => $otherProject->id,
        'agent_id' => $this->agent->id,
        'input' => ['message' => 'foreign'],
        'status' => 'completed',
        'visibility' => 'org',
        'created_by' => $this->other->id,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/work-feed');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($orgRun->id)
        ->and($ids)->toContain($teamRun->id)
        ->and(count($ids))->toBe(2);
});

it('work feed excludes running/pending runs', function () {
    run(['status' => 'running', 'visibility' => 'org']);
    run(['status' => 'pending', 'visibility' => 'org']);
    run(['status' => 'completed', 'visibility' => 'org']);

    $response = $this->actingAs($this->user)->getJson('/api/work-feed');

    expect(count($response->json('data')))->toBe(1);
});

it('fork creates a new pending private run linked to the original', function () {
    $original = run(['input' => ['message' => 'Fork me'], 'visibility' => 'org']);

    $response = $this->actingAs($this->other)
        ->postJson("/api/runs/{$original->id}/fork");

    $response->assertCreated();
    $forkedId = $response->json('data.id');
    $forked = ExecutionRun::find($forkedId);

    expect($forked->status)->toBe('pending')
        ->and($forked->visibility)->toBe('private')
        ->and($forked->forked_from_run_id)->toBe($original->id)
        ->and($forked->created_by)->toBe($this->other->id)
        ->and($forked->input)->toBe(['message' => 'Fork me']);
});

it('fork is blocked for private runs of other users', function () {
    $privateRun = run(['visibility' => 'private']);

    $this->actingAs($this->other)
        ->postJson("/api/runs/{$privateRun->id}/fork")
        ->assertForbidden();
});

it('visibility can be changed only by the creator', function () {
    $r = run(['visibility' => 'private']);

    $this->actingAs($this->other)
        ->putJson("/api/runs/{$r->id}/visibility", ['visibility' => 'org'])
        ->assertForbidden();

    $this->actingAs($this->user)
        ->putJson("/api/runs/{$r->id}/visibility", ['visibility' => 'org'])
        ->assertOk();

    expect($r->fresh()->visibility)->toBe('org');
});
