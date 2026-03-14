<?php

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->org = Organization::create([
        'name' => 'Test Org',
        'slug' => 'test-org-' . Str::random(4),
        'plan' => 'free',
    ]);
    $this->org->users()->attach($this->owner->id, [
        'role' => 'owner',
        'accepted_at' => now(),
    ]);
    $this->owner->update(['current_organization_id' => $this->org->id]);

    // Bind current org context for the BelongsToOrganization trait
    app()->instance('current_organization', $this->org);
});

// ─── Organization CRUD ────────────────────────────────────────

test('GET /api/organizations lists user organizations', function () {
    $this->actingAs($this->owner);

    $response = $this->getJson('/api/organizations');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.name', 'Test Org');
    $response->assertJsonPath('data.0.role', 'owner');
});

test('POST /api/organizations creates new organization', function () {
    $this->actingAs($this->owner);

    $response = $this->postJson('/api/organizations', [
        'name' => 'New Org',
        'description' => 'A new workspace',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'New Org');
    $response->assertJsonPath('data.description', 'A new workspace');
    $response->assertJsonPath('data.plan', 'free');
    $response->assertJsonPath('data.member_count', 1);

    expect($this->owner->organizations()->count())->toBe(2);
});

test('GET /api/organizations/{org} shows org details', function () {
    $this->actingAs($this->owner);

    $response = $this->getJson("/api/organizations/{$this->org->id}");

    $response->assertOk();
    $response->assertJsonPath('data.name', 'Test Org');
    $response->assertJsonPath('data.plan', 'free');
});

test('GET /api/organizations/{org} returns 403 for non-members', function () {
    $stranger = User::factory()->create();
    $this->actingAs($stranger);

    $response = $this->getJson("/api/organizations/{$this->org->id}");

    $response->assertForbidden();
});

test('PUT /api/organizations/{org} updates org name', function () {
    $this->actingAs($this->owner);

    $response = $this->putJson("/api/organizations/{$this->org->id}", [
        'name' => 'Renamed Org',
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.name', 'Renamed Org');
});

test('PUT /api/organizations/{org} requires admin role', function () {
    $viewer = User::factory()->create();
    $this->org->users()->attach($viewer->id, ['role' => 'viewer', 'accepted_at' => now()]);
    $this->actingAs($viewer);

    $response = $this->putJson("/api/organizations/{$this->org->id}", [
        'name' => 'Hacked',
    ]);

    $response->assertForbidden();
});

test('DELETE /api/organizations/{org} deletes org (owner only)', function () {
    $this->actingAs($this->owner);

    // Create a second org so it's not the last one
    $org2 = Organization::create(['name' => 'Second Org', 'slug' => 'second-org-' . Str::random(4), 'plan' => 'free']);
    $org2->users()->attach($this->owner->id, ['role' => 'owner', 'accepted_at' => now()]);

    $response = $this->deleteJson("/api/organizations/{$this->org->id}");

    $response->assertOk();
    expect(Organization::find($this->org->id))->toBeNull();
});

test('DELETE /api/organizations/{org} cannot delete last org', function () {
    $this->actingAs($this->owner);

    $response = $this->deleteJson("/api/organizations/{$this->org->id}");

    $response->assertStatus(422);
    $response->assertJsonPath('error', 'Cannot delete your last organization.');
});

test('DELETE /api/organizations/{org} requires owner role', function () {
    $admin = User::factory()->create();
    $this->org->users()->attach($admin->id, ['role' => 'admin', 'accepted_at' => now()]);
    $this->actingAs($admin);

    $response = $this->deleteJson("/api/organizations/{$this->org->id}");

    $response->assertForbidden();
});

// ─── Organization Switching ───────────────────────────────────

test('POST /api/organizations/{org}/switch switches current org', function () {
    $this->actingAs($this->owner);

    $org2 = Organization::create(['name' => 'Other Org', 'slug' => 'other-org-' . Str::random(4), 'plan' => 'free']);
    $org2->users()->attach($this->owner->id, ['role' => 'owner', 'accepted_at' => now()]);

    $response = $this->postJson("/api/organizations/{$org2->id}/switch");

    $response->assertOk();
    $this->owner->refresh();
    expect($this->owner->current_organization_id)->toBe($org2->id);
});

test('POST /api/organizations/{org}/switch returns 403 for non-members', function () {
    $stranger = User::factory()->create();
    $this->actingAs($stranger);

    $response = $this->postJson("/api/organizations/{$this->org->id}/switch");

    $response->assertForbidden();
});

// ─── Member Management ────────────────────────────────────────

test('GET /api/organizations/{org}/members lists members', function () {
    $this->actingAs($this->owner);

    $member = User::factory()->create();
    $this->org->users()->attach($member->id, ['role' => 'editor', 'accepted_at' => now()]);

    $response = $this->getJson("/api/organizations/{$this->org->id}/members");

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
});

test('PUT /api/organizations/{org}/members/{user} updates member role', function () {
    $this->actingAs($this->owner);

    $member = User::factory()->create();
    $this->org->users()->attach($member->id, ['role' => 'member', 'accepted_at' => now()]);

    $response = $this->putJson("/api/organizations/{$this->org->id}/members/{$member->id}", [
        'role' => 'editor',
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.role', 'editor');
});

test('PUT /api/organizations/{org}/members/{user} cannot change owner role', function () {
    $admin = User::factory()->create();
    $this->org->users()->attach($admin->id, ['role' => 'admin', 'accepted_at' => now()]);
    $this->actingAs($admin);

    $response = $this->putJson("/api/organizations/{$this->org->id}/members/{$this->owner->id}", [
        'role' => 'viewer',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error', "Cannot change the owner's role.");
});

test('DELETE /api/organizations/{org}/members/{user} removes member', function () {
    $this->actingAs($this->owner);

    $member = User::factory()->create();
    $this->org->users()->attach($member->id, ['role' => 'member', 'accepted_at' => now()]);

    $response = $this->deleteJson("/api/organizations/{$this->org->id}/members/{$member->id}");

    $response->assertOk();
    expect($this->org->users()->where('users.id', $member->id)->exists())->toBeFalse();
});

test('DELETE /api/organizations/{org}/members/{user} cannot remove owner', function () {
    $admin = User::factory()->create();
    $this->org->users()->attach($admin->id, ['role' => 'admin', 'accepted_at' => now()]);
    $this->actingAs($admin);

    $response = $this->deleteJson("/api/organizations/{$this->org->id}/members/{$this->owner->id}");

    $response->assertStatus(422);
    $response->assertJsonPath('error', 'Cannot remove the organization owner.');
});

// ─── Invitation System ────────────────────────────────────────

test('POST /api/organizations/{org}/invitations creates invitation', function () {
    $this->actingAs($this->owner);

    $response = $this->postJson("/api/organizations/{$this->org->id}/invitations", [
        'email' => 'newuser@example.com',
        'role' => 'editor',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.email', 'newuser@example.com');
    $response->assertJsonPath('data.role', 'editor');
    expect($response->json('data.token'))->not->toBeNull();

    expect(OrganizationInvitation::count())->toBe(1);
});

test('POST /api/organizations/{org}/invitations rejects duplicate pending invitation', function () {
    $this->actingAs($this->owner);

    OrganizationInvitation::create([
        'organization_id' => $this->org->id,
        'email' => 'existing@example.com',
        'role' => 'member',
        'invited_by' => $this->owner->id,
    ]);

    $response = $this->postJson("/api/organizations/{$this->org->id}/invitations", [
        'email' => 'existing@example.com',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error', 'An invitation has already been sent to this email.');
});

test('POST /api/organizations/{org}/invitations rejects existing members', function () {
    $this->actingAs($this->owner);

    $response = $this->postJson("/api/organizations/{$this->org->id}/invitations", [
        'email' => $this->owner->email,
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error', 'User is already a member of this organization.');
});

test('GET /api/organizations/{org}/invitations lists pending invitations', function () {
    $this->actingAs($this->owner);

    OrganizationInvitation::create([
        'organization_id' => $this->org->id,
        'email' => 'pending@example.com',
        'role' => 'member',
        'invited_by' => $this->owner->id,
    ]);

    // Expired invitation (should not appear)
    OrganizationInvitation::create([
        'organization_id' => $this->org->id,
        'email' => 'expired@example.com',
        'role' => 'member',
        'invited_by' => $this->owner->id,
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->getJson("/api/organizations/{$this->org->id}/invitations");

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.email', 'pending@example.com');
});

test('DELETE /api/invitations/{invitation} cancels invitation', function () {
    $this->actingAs($this->owner);

    $invitation = OrganizationInvitation::create([
        'organization_id' => $this->org->id,
        'email' => 'cancel@example.com',
        'role' => 'member',
        'invited_by' => $this->owner->id,
    ]);

    $response = $this->deleteJson("/api/invitations/{$invitation->id}");

    $response->assertOk();
    expect(OrganizationInvitation::find($invitation->id))->toBeNull();
});

test('POST /api/invitations/accept/{token} accepts invitation', function () {
    $invitee = User::factory()->create(['email' => 'invited@example.com']);
    // Give invitee an org so they can authenticate
    $inviteeOrg = Organization::create(['name' => 'Invitee Org', 'slug' => 'invitee-org-' . Str::random(4), 'plan' => 'free']);
    $inviteeOrg->users()->attach($invitee->id, ['role' => 'owner', 'accepted_at' => now()]);
    $invitee->update(['current_organization_id' => $inviteeOrg->id]);

    $this->actingAs($invitee);

    $invitation = OrganizationInvitation::create([
        'organization_id' => $this->org->id,
        'email' => 'invited@example.com',
        'role' => 'editor',
        'invited_by' => $this->owner->id,
    ]);

    $response = $this->postJson("/api/invitations/accept/{$invitation->token}");

    $response->assertOk();
    $response->assertJsonPath('message', 'Invitation accepted.');

    // Verify membership
    expect($this->org->users()->where('users.id', $invitee->id)->exists())->toBeTrue();
    expect($invitee->roleInOrganization($this->org))->toBe('editor');
});

test('POST /api/invitations/accept/{token} rejects mismatched email', function () {
    $wrongUser = User::factory()->create(['email' => 'wrong@example.com']);
    $wrongOrg = Organization::create(['name' => 'Wrong Org', 'slug' => 'wrong-org-' . Str::random(4), 'plan' => 'free']);
    $wrongOrg->users()->attach($wrongUser->id, ['role' => 'owner', 'accepted_at' => now()]);
    $wrongUser->update(['current_organization_id' => $wrongOrg->id]);

    $this->actingAs($wrongUser);

    $invitation = OrganizationInvitation::create([
        'organization_id' => $this->org->id,
        'email' => 'someone-else@example.com',
        'role' => 'member',
        'invited_by' => $this->owner->id,
    ]);

    $response = $this->postJson("/api/invitations/accept/{$invitation->token}");

    $response->assertForbidden();
});

test('POST /api/invitations/accept/{token} rejects expired invitation', function () {
    $invitee = User::factory()->create(['email' => 'expired-invite@example.com']);
    $inviteeOrg = Organization::create(['name' => 'Invitee Org 2', 'slug' => 'invitee-org2-' . Str::random(4), 'plan' => 'free']);
    $inviteeOrg->users()->attach($invitee->id, ['role' => 'owner', 'accepted_at' => now()]);
    $invitee->update(['current_organization_id' => $inviteeOrg->id]);

    $this->actingAs($invitee);

    $invitation = OrganizationInvitation::create([
        'organization_id' => $this->org->id,
        'email' => 'expired-invite@example.com',
        'role' => 'member',
        'invited_by' => $this->owner->id,
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->postJson("/api/invitations/accept/{$invitation->token}");

    $response->assertStatus(422);
});

// ─── OrganizationInvitation Model ─────────────────────────────

test('OrganizationInvitation auto-generates uuid, token, and expires_at', function () {
    $invitation = OrganizationInvitation::create([
        'organization_id' => $this->org->id,
        'email' => 'auto@example.com',
        'invited_by' => $this->owner->id,
    ]);

    expect($invitation->uuid)->not->toBeNull();
    expect(strlen($invitation->uuid))->toBe(36);
    expect($invitation->token)->not->toBeNull();
    expect(strlen($invitation->token))->toBe(64);
    expect($invitation->expires_at)->not->toBeNull();
    expect($invitation->expires_at->isFuture())->toBeTrue();
});

test('OrganizationInvitation pending scope works', function () {
    OrganizationInvitation::create([
        'organization_id' => $this->org->id,
        'email' => 'pending@test.com',
        'invited_by' => $this->owner->id,
    ]);

    OrganizationInvitation::create([
        'organization_id' => $this->org->id,
        'email' => 'expired@test.com',
        'invited_by' => $this->owner->id,
        'expires_at' => now()->subHour(),
    ]);

    OrganizationInvitation::create([
        'organization_id' => $this->org->id,
        'email' => 'accepted@test.com',
        'invited_by' => $this->owner->id,
        'accepted_at' => now(),
    ]);

    expect(OrganizationInvitation::pending()->count())->toBe(1);
    expect(OrganizationInvitation::pending()->first()->email)->toBe('pending@test.com');
});

test('OrganizationInvitation isExpired and isPending work', function () {
    $pending = OrganizationInvitation::create([
        'organization_id' => $this->org->id,
        'email' => 'check-pending@test.com',
        'invited_by' => $this->owner->id,
    ]);

    expect($pending->isExpired())->toBeFalse();
    expect($pending->isPending())->toBeTrue();

    $expired = OrganizationInvitation::create([
        'organization_id' => $this->org->id,
        'email' => 'check-expired@test.com',
        'invited_by' => $this->owner->id,
        'expires_at' => now()->subDay(),
    ]);

    expect($expired->isExpired())->toBeTrue();
    expect($expired->isPending())->toBeFalse();
});

// ─── Role Enforcement ─────────────────────────────────────────

test('viewer cannot create projects (org-role:editor)', function () {
    $viewer = User::factory()->create();
    $this->org->users()->attach($viewer->id, ['role' => 'viewer', 'accepted_at' => now()]);
    $viewer->update(['current_organization_id' => $this->org->id]);
    $this->actingAs($viewer);

    $response = $this->postJson('/api/projects', [
        'name' => 'Denied Project',
        'path' => '/tmp/denied',
    ]);

    $response->assertForbidden();
    $response->assertJsonPath('error', 'Insufficient permissions. Required role: editor');
});

test('member cannot create projects (org-role:editor)', function () {
    $member = User::factory()->create();
    $this->org->users()->attach($member->id, ['role' => 'member', 'accepted_at' => now()]);
    $member->update(['current_organization_id' => $this->org->id]);
    $this->actingAs($member);

    $response = $this->postJson('/api/projects', [
        'name' => 'Denied Project',
        'path' => '/tmp/denied',
    ]);

    $response->assertForbidden();
});

test('editor can create projects', function () {
    $editor = User::factory()->create();
    $this->org->users()->attach($editor->id, ['role' => 'editor', 'accepted_at' => now()]);
    $editor->update(['current_organization_id' => $this->org->id]);
    $this->actingAs($editor);

    $response = $this->postJson('/api/projects', [
        'name' => 'Allowed Project',
        'path' => '/tmp/allowed',
    ]);

    // Should not be 403 — the middleware passed. May be 201 or 422 (path validation).
    expect($response->status())->not->toBe(403);
});

test('editor cannot update settings (org-role:admin)', function () {
    $editor = User::factory()->create();
    $this->org->users()->attach($editor->id, ['role' => 'editor', 'accepted_at' => now()]);
    $editor->update(['current_organization_id' => $this->org->id]);
    $this->actingAs($editor);

    $response = $this->putJson('/api/settings', [
        'default_model' => 'gpt-5.4',
    ]);

    $response->assertForbidden();
});

test('admin can update settings', function () {
    $admin = User::factory()->create();
    $this->org->users()->attach($admin->id, ['role' => 'admin', 'accepted_at' => now()]);
    $admin->update(['current_organization_id' => $this->org->id]);
    $this->actingAs($admin);

    $response = $this->putJson('/api/settings', [
        'default_model' => 'gpt-5.4',
    ]);

    $response->assertOk();
});

// ─── Data Isolation ───────────────────────────────────────────

test('user in org A cannot see org B projects', function () {
    // Create org B with a project
    $userB = User::factory()->create();
    $orgB = Organization::create(['name' => 'Org B', 'slug' => 'org-b-' . Str::random(4), 'plan' => 'free']);
    $orgB->users()->attach($userB->id, ['role' => 'owner', 'accepted_at' => now()]);
    $userB->update(['current_organization_id' => $orgB->id]);

    // Set org B context and create a project
    app()->instance('current_organization', $orgB);
    $projectB = Project::create(['name' => 'Org B Project', 'path' => '/tmp/org-b-project', 'organization_id' => $orgB->id]);

    // Create a project in org A
    app()->instance('current_organization', $this->org);
    $projectA = Project::create(['name' => 'Org A Project', 'path' => '/tmp/org-a-project', 'organization_id' => $this->org->id]);

    // Acting as owner of org A, list projects
    $this->actingAs($this->owner);
    app()->instance('current_organization', $this->org);

    $response = $this->getJson('/api/projects');

    $response->assertOk();
    $projectNames = collect($response->json('data'))->pluck('name')->toArray();
    expect($projectNames)->toContain('Org A Project');
    expect($projectNames)->not->toContain('Org B Project');
});

test('user in org B cannot see org A projects', function () {
    $userB = User::factory()->create();
    $orgB = Organization::create(['name' => 'Org B Isolated', 'slug' => 'org-b-isolated-' . Str::random(4), 'plan' => 'free']);
    $orgB->users()->attach($userB->id, ['role' => 'owner', 'accepted_at' => now()]);
    $userB->update(['current_organization_id' => $orgB->id]);

    // Create project in org A
    app()->instance('current_organization', $this->org);
    Project::create(['name' => 'Secret A Project', 'path' => '/tmp/secret-a', 'organization_id' => $this->org->id]);

    // Acting as user B
    $this->actingAs($userB);
    app()->instance('current_organization', $orgB);

    $response = $this->getJson('/api/projects');

    $response->assertOk();
    $projectNames = collect($response->json('data'))->pluck('name')->toArray();
    expect($projectNames)->not->toContain('Secret A Project');
});

// ─── CheckOrganizationRole Middleware ─────────────────────────

test('CheckOrganizationRole allows when no org context', function () {
    // Unbind the organization context
    app()->forgetInstance('current_organization');

    $user = User::factory()->create();
    $this->actingAs($user);

    // Without org context, the middleware should pass through
    $response = $this->postJson('/api/projects', [
        'name' => 'No Org Context',
        'path' => '/tmp/no-org',
    ]);

    // Should not get 403 — may get validation error or success
    expect($response->status())->not->toBe(403);
});

test('role hierarchy is enforced correctly', function () {
    // Owner should be able to do everything an editor can
    $this->actingAs($this->owner);

    $response = $this->postJson('/api/projects', [
        'name' => 'Owner Creates',
        'path' => '/tmp/owner-project',
    ]);

    // Should not be 403 — the role check passed. May be 201 or 422 (path validation).
    expect($response->status())->not->toBe(403);
});
