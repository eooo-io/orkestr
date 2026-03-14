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

it('creates a project with new agent fields', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/projects', [
            'name' => 'Agent Team Alpha',
            'description' => 'A team of agents',
            'default_model' => 'claude-sonnet-4-6',
            'environment' => 'staging',
            'monthly_budget_usd' => 50.00,
            'icon' => "\u{1F916}",
            'color' => '#3B82F6',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Agent Team Alpha');
    $response->assertJsonPath('data.default_model', 'claude-sonnet-4-6');
    $response->assertJsonPath('data.environment', 'staging');
    expect((float) $response->json('data.monthly_budget_usd'))->toBe(50.0);
    $response->assertJsonPath('data.icon', "\u{1F916}");
    $response->assertJsonPath('data.color', '#3B82F6');
    $response->assertJsonPath('data.path', null);
});

it('creates a project without a path', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/projects', [
            'name' => 'No Path Project',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'No Path Project');
    $response->assertJsonPath('data.path', null);
    $response->assertJsonPath('data.environment', 'development');
});

it('updates project agent fields', function () {
    $project = Project::create([
        'name' => 'Old Project',
        'environment' => 'development',
    ]);

    $response = $this->actingAs($this->user)
        ->putJson("/api/projects/{$project->id}", [
            'default_model' => 'gpt-5.4',
            'environment' => 'production',
            'monthly_budget_usd' => 100.50,
            'icon' => "\u{1F680}",
            'color' => '#EF4444',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.default_model', 'gpt-5.4');
    $response->assertJsonPath('data.environment', 'production');
    $response->assertJsonPath('data.monthly_budget_usd', 100.5);
    $response->assertJsonPath('data.icon', "\u{1F680}");
    $response->assertJsonPath('data.color', '#EF4444');
});

it('returns new fields in project show response', function () {
    $project = Project::create([
        'name' => 'Full Project',
        'default_model' => 'claude-opus-4-6',
        'environment' => 'staging',
        'monthly_budget_usd' => 25.00,
        'icon' => "\u{1F9E0}",
        'color' => '#10B981',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/projects/{$project->id}");

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            'id', 'uuid', 'name', 'description', 'path',
            'default_model', 'monthly_budget_usd', 'environment',
            'icon', 'color',
        ],
    ]);
    $response->assertJsonPath('data.default_model', 'claude-opus-4-6');
    $response->assertJsonPath('data.environment', 'staging');
});
