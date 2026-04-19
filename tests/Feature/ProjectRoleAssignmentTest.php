<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectRoleAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::firstOrCreate(
        ['email' => 'role-admin@test.com'],
        ['name' => 'Role Admin', 'password' => bcrypt('password')],
    );

    $this->member = User::firstOrCreate(
        ['email' => 'member@test.com'],
        ['name' => 'Member', 'password' => bcrypt('password')],
    );

    $this->org = Organization::create(['name' => 'Role Org']);
    $this->admin->current_organization_id = $this->org->id;
    $this->admin->save();

    \DB::table('organization_user')->insert([
        'organization_id' => $this->org->id,
        'user_id' => $this->admin->id,
        'role' => 'admin',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->project = Project::create([
        'name' => 'Role Project',
        'path' => '/tmp/role',
        'providers' => ['claude'],
        'owner_id' => $this->admin->id,
        'organization_id' => $this->org->id,
    ]);
});

it('creates a DRI assignment with scope', function () {
    $response = $this->actingAs($this->admin)
        ->postJson("/api/projects/{$this->project->id}/role-assignments", [
            'user_id' => $this->member->id,
            'role' => 'dri',
            'scope' => 'merchant-churn',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.role', 'dri')
        ->assertJsonPath('data.scope', 'merchant-churn')
        ->assertJsonPath('data.user.email', 'member@test.com');
});

it('lists active assignments and filters out ended ones', function () {
    $activeId = ProjectRoleAssignment::create([
        'project_id' => $this->project->id,
        'user_id' => $this->member->id,
        'role' => 'ic',
        'started_at' => now()->subDay(),
    ])->id;

    ProjectRoleAssignment::create([
        'project_id' => $this->project->id,
        'user_id' => $this->member->id,
        'role' => 'coach',
        'started_at' => now()->subMonth(),
        'ended_at' => now()->subWeek(),
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson("/api/projects/{$this->project->id}/role-assignments");

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$activeId]);
});

it('a user can hold multiple active roles', function () {
    $this->actingAs($this->admin)
        ->postJson("/api/projects/{$this->project->id}/role-assignments", [
            'user_id' => $this->member->id, 'role' => 'ic',
        ])->assertCreated();

    $this->actingAs($this->admin)
        ->postJson("/api/projects/{$this->project->id}/role-assignments", [
            'user_id' => $this->member->id, 'role' => 'coach',
        ])->assertCreated();

    expect(ProjectRoleAssignment::where('user_id', $this->member->id)->count())->toBe(2);
});

it('destroy marks an assignment as ended instead of deleting the row', function () {
    $assignment = ProjectRoleAssignment::create([
        'project_id' => $this->project->id,
        'user_id' => $this->member->id,
        'role' => 'dri',
        'scope' => 'pricing',
        'started_at' => now()->subWeek(),
    ]);

    $this->actingAs($this->admin)
        ->deleteJson("/api/projects/{$this->project->id}/role-assignments/{$assignment->id}")
        ->assertOk();

    expect(ProjectRoleAssignment::find($assignment->id)->ended_at)->not->toBeNull();
});

it('rejects unknown role values', function () {
    $response = $this->actingAs($this->admin)
        ->postJson("/api/projects/{$this->project->id}/role-assignments", [
            'user_id' => $this->member->id,
            'role' => 'lead',
        ]);

    $response->assertStatus(422);
});

it('update can transfer ownership of a role to a different scope', function () {
    $assignment = ProjectRoleAssignment::create([
        'project_id' => $this->project->id,
        'user_id' => $this->member->id,
        'role' => 'dri',
        'scope' => 'pricing',
    ]);

    $this->actingAs($this->admin)
        ->putJson("/api/projects/{$this->project->id}/role-assignments/{$assignment->id}", [
            'scope' => 'merchant-churn',
        ])->assertOk();

    expect($assignment->fresh()->scope)->toBe('merchant-churn');
});
