<?php

use App\Models\Notification;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SkillAnalytic;
use App\Models\SkillReview;
use App\Models\SkillTestCase;
use App\Models\User;
use App\Services\CrossModelBenchmarkService;
use App\Services\NotificationService;
use App\Services\ReportExportService;
use App\Services\SkillAnalyticsService;
use App\Services\SkillInheritanceService;
use App\Services\SkillOwnershipService;
use App\Services\SkillRegressionService;
use App\Services\SkillReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->org = Organization::create([
        'name' => 'E6 Org',
        'slug' => 'e6-org',
        'plan' => 'teams',
    ]);
    $this->org->users()->attach($this->user->id, [
        'role' => 'owner',
        'accepted_at' => now(),
    ]);
    $this->user->update(['current_organization_id' => $this->org->id]);
    app()->instance('current_organization', $this->org);

    $this->project = Project::create([
        'name' => 'E6 Test Project',
        'path' => '/tmp/e6-test',
        'organization_id' => $this->org->id,
    ]);

    $this->skill = Skill::create([
        'project_id' => $this->project->id,
        'name' => 'Test Skill',
        'slug' => 'test-skill',
        'body' => 'You are a test assistant.',
    ]);
});

// ─── #219: Skill Review and Approval Workflow ───────────────────

test('can submit a skill for review', function () {
    $this->postJson("/api/skills/{$this->skill->id}/reviews", [
        'comments' => 'Please review this skill.',
    ])->assertCreated()
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath('skill_id', $this->skill->id);
});

test('can list reviews for a skill', function () {
    SkillReview::create([
        'skill_id' => $this->skill->id,
        'status' => 'pending',
        'comments' => 'Review me',
        'submitted_by' => $this->user->id,
    ]);

    $this->getJson("/api/skills/{$this->skill->id}/reviews")
        ->assertOk()
        ->assertJsonCount(1);
});

test('can approve a review', function () {
    $review = SkillReview::create([
        'skill_id' => $this->skill->id,
        'status' => 'pending',
        'submitted_by' => $this->user->id,
    ]);

    $this->postJson("/api/skill-reviews/{$review->id}/approve", [
        'comments' => 'Looks good!',
    ])->assertOk()
        ->assertJsonPath('status', 'approved');
});

test('can reject a review', function () {
    $review = SkillReview::create([
        'skill_id' => $this->skill->id,
        'status' => 'pending',
        'submitted_by' => $this->user->id,
    ]);

    $this->postJson("/api/skill-reviews/{$review->id}/reject", [
        'comments' => 'Needs improvement.',
    ])->assertOk()
        ->assertJsonPath('status', 'rejected');
});

test('SkillReviewService submit creates pending review', function () {
    $service = app(SkillReviewService::class);
    $review = $service->submit($this->skill, $this->user, null, 'Test comment');

    expect($review->status)->toBe('pending');
    expect($review->submitted_by)->toBe($this->user->id);
    expect($review->comments)->toBe('Test comment');
});

test('SkillReviewService pendingReviews returns only pending', function () {
    $service = app(SkillReviewService::class);
    $service->submit($this->skill, $this->user);

    SkillReview::create([
        'skill_id' => $this->skill->id,
        'status' => 'approved',
        'submitted_by' => $this->user->id,
    ]);

    $pending = $service->pendingReviews();
    expect($pending)->toHaveCount(1);
    expect($pending->first()->status)->toBe('pending');
});

// ─── #220: Skill Ownership and CODEOWNERS ───────────────────────

test('can get skill ownership', function () {
    $this->skill->update(['owner_id' => $this->user->id]);

    $this->getJson("/api/skills/{$this->skill->id}/ownership")
        ->assertOk()
        ->assertJsonPath('owner_id', $this->user->id);
});

test('can update skill ownership', function () {
    $this->putJson("/api/skills/{$this->skill->id}/ownership", [
        'owner_id' => $this->user->id,
        'codeowners' => [['email' => 'dev@example.com', 'pattern' => '*.md']],
    ])->assertOk()
        ->assertJsonPath('owner_id', $this->user->id);

    $this->skill->refresh();
    expect($this->skill->codeowners)->toHaveCount(1);
    expect($this->skill->codeowners[0]['email'])->toBe('dev@example.com');
});

test('SkillOwnershipService isOwner checks primary owner', function () {
    $this->skill->update(['owner_id' => $this->user->id]);

    $service = new SkillOwnershipService;
    expect($service->isOwner($this->skill, $this->user))->toBeTrue();

    $other = User::factory()->create();
    expect($service->isOwner($this->skill, $other))->toBeFalse();
});

test('SkillOwnershipService isOwner checks codeowners', function () {
    $this->skill->update([
        'codeowners' => [['email' => $this->user->email, 'pattern' => '*']],
    ]);

    $service = new SkillOwnershipService;
    expect($service->isOwner($this->skill, $this->user))->toBeTrue();
});

test('SkillOwnershipService autoAssignReviewer picks owner', function () {
    $this->skill->update(['owner_id' => $this->user->id]);
    $other = User::factory()->create();

    $service = new SkillOwnershipService;
    expect($service->autoAssignReviewer($this->skill, $other))->toBe($this->user->id);
});

test('SkillOwnershipService autoAssignReviewer excludes submitter', function () {
    $this->skill->update(['owner_id' => $this->user->id]);

    $service = new SkillOwnershipService;
    expect($service->autoAssignReviewer($this->skill, $this->user))->toBeNull();
});

// ─── #223: Notifications ────────────────────────────────────────

test('can list notifications', function () {
    Notification::create([
        'user_id' => $this->user->id,
        'type' => 'test',
        'title' => 'Test Notification',
        'created_at' => now(),
    ]);

    $this->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonPath('unread_count', 1);
});

test('can mark all notifications as read', function () {
    Notification::create([
        'user_id' => $this->user->id,
        'type' => 'test',
        'title' => 'Notification 1',
        'created_at' => now(),
    ]);
    Notification::create([
        'user_id' => $this->user->id,
        'type' => 'test',
        'title' => 'Notification 2',
        'created_at' => now(),
    ]);

    $this->postJson('/api/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('marked_read', 2);
});

test('can mark single notification as read', function () {
    $notification = Notification::create([
        'user_id' => $this->user->id,
        'type' => 'test',
        'title' => 'Read me',
        'created_at' => now(),
    ]);

    $this->postJson("/api/notifications/{$notification->id}/read")
        ->assertOk();

    $notification->refresh();
    expect($notification->read_at)->not->toBeNull();
});

test('NotificationService notify creates notification', function () {
    $service = new NotificationService;
    $notification = $service->notify($this->user->id, 'test', 'Hello', 'World');

    expect($notification->id)->not->toBeNull();
    expect($notification->title)->toBe('Hello');
    expect($notification->body)->toBe('World');
});

test('NotificationService unreadCount works', function () {
    $service = new NotificationService;
    $service->notify($this->user->id, 'test', 'One');
    $service->notify($this->user->id, 'test', 'Two');

    expect($service->unreadCount($this->user->id))->toBe(2);

    $service->markAllRead($this->user->id);
    expect($service->unreadCount($this->user->id))->toBe(0);
});

test('NotificationService notifyOwners notifies skill owner', function () {
    $this->skill->update(['owner_id' => $this->user->id]);

    $service = new NotificationService;
    $results = $service->notifyOwners($this->skill, 'change', 'Skill changed');

    expect($results)->toHaveCount(1);
    expect($results[0]->user_id)->toBe($this->user->id);
});

// ─── #225: Skill Analytics Dashboard ────────────────────────────

test('SkillAnalyticsService records and retrieves stats', function () {
    $service = new SkillAnalyticsService;
    $service->record($this->skill->id, true, 500, 150, 1200);
    $service->record($this->skill->id, false, 600, 200, 1500);

    $stats = $service->getSkillStats($this->skill->id);

    expect($stats['total_runs'])->toBe(2);
    expect($stats['total_pass'])->toBe(1);
    expect($stats['total_fail'])->toBe(1);
    expect($stats['pass_rate'])->toBe(50.0);
});

test('can get skill analytics via API', function () {
    SkillAnalytic::create([
        'skill_id' => $this->skill->id,
        'date' => now()->toDateString(),
        'test_runs' => 10,
        'pass_count' => 8,
        'fail_count' => 2,
    ]);

    $this->getJson("/api/skills/{$this->skill->id}/analytics")
        ->assertOk()
        ->assertJsonPath('total_runs', 10);
});

test('can get top skills analytics', function () {
    SkillAnalytic::create([
        'skill_id' => $this->skill->id,
        'date' => now()->toDateString(),
        'test_runs' => 25,
        'pass_count' => 20,
        'fail_count' => 5,
    ]);

    $this->getJson('/api/analytics/top-skills')
        ->assertOk();
});

test('can get analytics trends', function () {
    SkillAnalytic::create([
        'skill_id' => $this->skill->id,
        'date' => now()->toDateString(),
        'test_runs' => 5,
        'pass_count' => 4,
        'fail_count' => 1,
    ]);

    $this->getJson('/api/analytics/trends')
        ->assertOk();
});

test('SkillAnalyticsService getTopSkills returns ranked list', function () {
    $skill2 = Skill::create([
        'project_id' => $this->project->id,
        'name' => 'Popular Skill',
        'slug' => 'popular-skill',
        'body' => 'Popular.',
    ]);

    SkillAnalytic::create(['skill_id' => $this->skill->id, 'date' => now()->toDateString(), 'test_runs' => 5, 'pass_count' => 5, 'fail_count' => 0]);
    SkillAnalytic::create(['skill_id' => $skill2->id, 'date' => now()->toDateString(), 'test_runs' => 20, 'pass_count' => 18, 'fail_count' => 2]);

    $service = new SkillAnalyticsService;
    $top = $service->getTopSkills(10);

    expect($top->first()['skill_id'])->toBe($skill2->id);
    expect($top->first()['total_runs'])->toBe(20);
});

// ─── #227: Regression Testing ───────────────────────────────────

test('can create a test case', function () {
    $this->postJson("/api/skills/{$this->skill->id}/test-cases", [
        'name' => 'Basic test',
        'input' => 'Hello world',
        'expected_output' => 'response',
        'assertion_type' => 'contains',
    ])->assertCreated()
        ->assertJsonPath('name', 'Basic test');
});

test('can list test cases', function () {
    SkillTestCase::create([
        'skill_id' => $this->skill->id,
        'name' => 'Test 1',
        'input' => 'Input 1',
        'assertion_type' => 'contains',
    ]);

    $this->getJson("/api/skills/{$this->skill->id}/test-cases")
        ->assertOk()
        ->assertJsonCount(1);
});

test('can update a test case', function () {
    $tc = SkillTestCase::create([
        'skill_id' => $this->skill->id,
        'name' => 'Old Name',
        'input' => 'Input',
    ]);

    $this->putJson("/api/skill-test-cases/{$tc->id}", [
        'name' => 'New Name',
    ])->assertOk()
        ->assertJsonPath('name', 'New Name');
});

test('can delete a test case', function () {
    $tc = SkillTestCase::create([
        'skill_id' => $this->skill->id,
        'name' => 'Deletable',
        'input' => 'Input',
    ]);

    $this->deleteJson("/api/skill-test-cases/{$tc->id}")
        ->assertOk();

    expect(SkillTestCase::find($tc->id))->toBeNull();
});

test('can run all test cases', function () {
    $tc = SkillTestCase::create([
        'skill_id' => $this->skill->id,
        'name' => 'Contains test',
        'input' => 'Hello',
        'expected_output' => 'world',
        'assertion_type' => 'contains',
    ]);

    $this->postJson("/api/skills/{$this->skill->id}/test-cases/run-all", [
        'outputs' => [$tc->id => 'Hello world!'],
    ])->assertOk()
        ->assertJsonPath('passed', 1)
        ->assertJsonPath('failed', 0);
});

test('SkillRegressionService contains assertion works', function () {
    $tc = SkillTestCase::create([
        'skill_id' => $this->skill->id,
        'name' => 'Contains',
        'input' => 'test',
        'expected_output' => 'hello',
        'assertion_type' => 'contains',
    ]);

    $service = new SkillRegressionService;
    $result = $service->runTestCase($tc, 'Say hello world');
    expect($result['passed'])->toBeTrue();

    $result2 = $service->runTestCase($tc, 'Goodbye');
    expect($result2['passed'])->toBeFalse();
});

test('SkillRegressionService equals assertion works', function () {
    $tc = SkillTestCase::create([
        'skill_id' => $this->skill->id,
        'name' => 'Equals',
        'input' => 'test',
        'expected_output' => 'exact match',
        'assertion_type' => 'equals',
    ]);

    $service = new SkillRegressionService;
    expect($service->runTestCase($tc, 'exact match')['passed'])->toBeTrue();
    expect($service->runTestCase($tc, 'not exact match')['passed'])->toBeFalse();
});

test('SkillRegressionService not_contains assertion works', function () {
    $tc = SkillTestCase::create([
        'skill_id' => $this->skill->id,
        'name' => 'Not Contains',
        'input' => 'test',
        'expected_output' => 'error',
        'assertion_type' => 'not_contains',
    ]);

    $service = new SkillRegressionService;
    expect($service->runTestCase($tc, 'All good')['passed'])->toBeTrue();
    expect($service->runTestCase($tc, 'Has error inside')['passed'])->toBeFalse();
});

test('SkillRegressionService regex assertion works', function () {
    $tc = SkillTestCase::create([
        'skill_id' => $this->skill->id,
        'name' => 'Regex',
        'input' => 'test',
        'expected_output' => '/^\d{3}-\d{4}$/',
        'assertion_type' => 'regex',
    ]);

    $service = new SkillRegressionService;
    expect($service->runTestCase($tc, '123-4567')['passed'])->toBeTrue();
    expect($service->runTestCase($tc, 'not a number')['passed'])->toBeFalse();
});

// ─── #231: Skill Inheritance ────────────────────────────────────

test('can set skill inheritance', function () {
    $parent = Skill::create([
        'project_id' => $this->project->id,
        'name' => 'Parent Skill',
        'slug' => 'parent-skill',
        'body' => 'Parent instructions.',
    ]);

    $this->putJson("/api/skills/{$this->skill->id}/inheritance", [
        'extends_skill_id' => $parent->id,
    ])->assertOk()
        ->assertJsonPath('extends_skill_id', $parent->id);
});

test('cannot set self-inheritance', function () {
    $this->putJson("/api/skills/{$this->skill->id}/inheritance", [
        'extends_skill_id' => $this->skill->id,
    ])->assertStatus(422);
});

test('SkillInheritanceService resolve merges parent and child', function () {
    $parent = Skill::create([
        'project_id' => $this->project->id,
        'name' => 'Parent',
        'slug' => 'parent-e6',
        'body' => 'Parent body.',
        'model' => 'claude-sonnet-4-6',
        'max_tokens' => 2000,
    ]);

    $this->skill->update([
        'extends_skill_id' => $parent->id,
        'model' => 'gpt-5.4',
    ]);

    $service = new SkillInheritanceService;
    $resolved = $service->resolve($this->skill);

    expect($resolved['frontmatter']['model'])->toBe('gpt-5.4'); // child overrides
    expect($resolved['frontmatter']['max_tokens'])->toBe(2000); // inherited from parent
    expect($resolved['inheritance_chain'])->toHaveCount(2);
});

test('SkillInheritanceService getChildren works', function () {
    $child = Skill::create([
        'project_id' => $this->project->id,
        'name' => 'Child',
        'slug' => 'child-e6',
        'body' => 'Child body.',
        'extends_skill_id' => $this->skill->id,
    ]);

    $service = new SkillInheritanceService;
    $children = $service->getChildren($this->skill);

    expect($children)->toHaveCount(1);
    expect($children[0]['id'])->toBe($child->id);
});

test('SkillInheritanceService respects max depth', function () {
    $skills = [$this->skill];
    for ($i = 0; $i < 7; $i++) {
        $child = Skill::create([
            'project_id' => $this->project->id,
            'name' => "Depth {$i}",
            'slug' => "depth-{$i}",
            'body' => "Depth {$i} body.",
            'extends_skill_id' => $skills[count($skills) - 1]->id,
        ]);
        $skills[] = $child;
    }

    $service = new SkillInheritanceService;
    $chain = $service->getInheritanceChain($skills[count($skills) - 1]);

    // Max depth is 5, so chain should be at most 6 (self + 5 ancestors)
    expect(count($chain))->toBeLessThanOrEqual(6);
});

test('can get skill children via API', function () {
    Skill::create([
        'project_id' => $this->project->id,
        'name' => 'Child Skill',
        'slug' => 'child-api',
        'body' => 'Child.',
        'extends_skill_id' => $this->skill->id,
    ]);

    $this->getJson("/api/skills/{$this->skill->id}/children")
        ->assertOk()
        ->assertJsonCount(1);
});

test('can resolve inherited skill via API', function () {
    $this->getJson("/api/skills/{$this->skill->id}/resolve")
        ->assertOk()
        ->assertJsonStructure(['frontmatter', 'body', 'inheritance_chain']);
});

// ─── #240: Export Reports ───────────────────────────────────────

test('can export skill inventory as CSV', function () {
    $response = $this->get('/api/reports/skills');
    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

test('can export usage report as CSV', function () {
    SkillAnalytic::create([
        'skill_id' => $this->skill->id,
        'date' => now()->toDateString(),
        'test_runs' => 5,
        'pass_count' => 4,
        'fail_count' => 1,
    ]);

    $response = $this->get('/api/reports/usage');
    $response->assertOk();
});

test('can export audit report as CSV', function () {
    $response = $this->get('/api/reports/audit');
    $response->assertOk();
});

test('ReportExportService exportSkillInventory returns data', function () {
    $service = new ReportExportService;
    $data = $service->exportSkillInventory();

    expect($data)->toHaveCount(1);
    expect($data[0]['name'])->toBe('Test Skill');
});

// ─── #230: Cross-Model Benchmarking ─────────────────────────────

test('benchmark API validates models array', function () {
    $this->postJson("/api/skills/{$this->skill->id}/benchmark", [
        'models' => [],
    ])->assertStatus(422);
});

test('benchmark API requires models field', function () {
    $this->postJson("/api/skills/{$this->skill->id}/benchmark", [])
        ->assertStatus(422);
});

// ─── #241: GitHub Org Import ────────────────────────────────────

test('github discover API validates organization field', function () {
    $this->postJson('/api/import/github/discover', [])
        ->assertStatus(422);
});

test('github import API validates repo field', function () {
    $this->postJson('/api/import/github/import', [])
        ->assertStatus(422);
});

// ─── Infrastructure ─────────────────────────────────────────────

test('E6 migration creates all tables', function () {
    expect(\Illuminate\Support\Facades\Schema::hasTable('skill_reviews'))->toBeTrue();
    expect(\Illuminate\Support\Facades\Schema::hasTable('notifications'))->toBeTrue();
    expect(\Illuminate\Support\Facades\Schema::hasTable('skill_analytics'))->toBeTrue();
    expect(\Illuminate\Support\Facades\Schema::hasTable('skill_test_cases'))->toBeTrue();
});

test('skills table has new ownership and inheritance columns', function () {
    expect(\Illuminate\Support\Facades\Schema::hasColumn('skills', 'owner_id'))->toBeTrue();
    expect(\Illuminate\Support\Facades\Schema::hasColumn('skills', 'codeowners'))->toBeTrue();
    expect(\Illuminate\Support\Facades\Schema::hasColumn('skills', 'extends_skill_id'))->toBeTrue();
    expect(\Illuminate\Support\Facades\Schema::hasColumn('skills', 'override_sections'))->toBeTrue();
});

test('SkillReview model relationships work', function () {
    $review = SkillReview::create([
        'skill_id' => $this->skill->id,
        'status' => 'pending',
        'submitted_by' => $this->user->id,
        'reviewer_id' => $this->user->id,
    ]);

    expect($review->skill->id)->toBe($this->skill->id);
    expect($review->submitter->id)->toBe($this->user->id);
    expect($review->reviewer->id)->toBe($this->user->id);
});

test('Notification model scopes work', function () {
    Notification::create([
        'user_id' => $this->user->id,
        'type' => 'test',
        'title' => 'Unread',
        'created_at' => now(),
    ]);
    Notification::create([
        'user_id' => $this->user->id,
        'type' => 'test',
        'title' => 'Read',
        'read_at' => now(),
        'created_at' => now(),
    ]);

    expect(Notification::unread()->count())->toBe(1);
});

test('SkillReview scopes filter correctly', function () {
    SkillReview::create(['skill_id' => $this->skill->id, 'status' => 'pending', 'submitted_by' => $this->user->id]);
    SkillReview::create(['skill_id' => $this->skill->id, 'status' => 'approved', 'submitted_by' => $this->user->id]);
    SkillReview::create(['skill_id' => $this->skill->id, 'status' => 'rejected', 'submitted_by' => $this->user->id]);

    expect(SkillReview::pending()->count())->toBe(1);
    expect(SkillReview::approved()->count())->toBe(1);
    expect(SkillReview::rejected()->count())->toBe(1);
});

test('review approval creates notification for submitter', function () {
    $submitter = User::factory()->create();
    $review = SkillReview::create([
        'skill_id' => $this->skill->id,
        'status' => 'pending',
        'submitted_by' => $submitter->id,
    ]);

    $service = app(SkillReviewService::class);
    $service->approve($review, $this->user, 'Approved!');

    $notifications = Notification::where('user_id', $submitter->id)->get();
    expect($notifications)->toHaveCount(1);
    expect($notifications->first()->type)->toBe('skill_review_approved');
});

test('review rejection creates notification for submitter', function () {
    $submitter = User::factory()->create();
    $review = SkillReview::create([
        'skill_id' => $this->skill->id,
        'status' => 'pending',
        'submitted_by' => $submitter->id,
    ]);

    $service = app(SkillReviewService::class);
    $service->reject($review, $this->user, 'Needs work.');

    $notifications = Notification::where('user_id', $submitter->id)->get();
    expect($notifications)->toHaveCount(1);
    expect($notifications->first()->type)->toBe('skill_review_rejected');
});
