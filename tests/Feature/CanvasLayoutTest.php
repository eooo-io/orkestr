<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::firstOrCreate(
        ['email' => 'test@test.com'],
        ['name' => 'Test User', 'password' => bcrypt('password')],
    );
});

it('saves canvas layout for a project', function () {
    $project = Project::create(['name' => 'Canvas Test']);

    $layout = [
        'nodes' => [
            'agent-1' => ['x' => 100, 'y' => 200],
            'agent-2' => ['x' => 300, 'y' => 400],
        ],
    ];

    $response = $this->actingAs($this->user)
        ->putJson("/api/projects/{$project->id}/canvas-layout", $layout);

    $response->assertOk();
    $response->assertJsonPath('data.nodes.agent-1.x', 100);
    $response->assertJsonPath('data.nodes.agent-1.y', 200);
    $response->assertJsonPath('data.nodes.agent-2.x', 300);
    $response->assertJsonPath('data.nodes.agent-2.y', 400);

    // Verify it was persisted
    $project->refresh();
    expect($project->canvas_layout)->toBe($layout);
});

it('fetches canvas layout for a project', function () {
    $layout = [
        'nodes' => [
            'skill-1' => ['x' => 50, 'y' => 75],
        ],
    ];

    $project = Project::create([
        'name' => 'Fetch Layout Test',
        'canvas_layout' => $layout,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/projects/{$project->id}/canvas-layout");

    $response->assertOk();
    $response->assertJsonPath('data.nodes.skill-1.x', 50);
    $response->assertJsonPath('data.nodes.skill-1.y', 75);
});

it('returns null layout for new projects', function () {
    $project = Project::create(['name' => 'New Project']);

    $response = $this->actingAs($this->user)
        ->getJson("/api/projects/{$project->id}/canvas-layout");

    $response->assertOk();
    $response->assertJsonPath('data', null);
});

it('cannot access another projects canvas layout without auth', function () {
    $project = Project::create([
        'name' => 'Private Project',
        'canvas_layout' => ['nodes' => ['a' => ['x' => 1, 'y' => 2]]],
    ]);

    $response = $this->getJson("/api/projects/{$project->id}/canvas-layout");

    $response->assertUnauthorized();
});
