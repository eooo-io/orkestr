<?php

use App\Models\Agent;
use App\Models\Project;
use App\Models\ProjectAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::firstOrCreate(
        ['email' => 'test@test.com'],
        ['name' => 'Test User', 'password' => bcrypt('password')],
    );
    $this->project = Project::create(['name' => 'Test Project']);
});

it('quick-creates an agent with name only', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/agents/quick-create", [
            'name' => 'My New Agent',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'My New Agent');
    $response->assertJsonPath('data.role', 'general');
    $response->assertJsonPath('data.model', 'claude-sonnet-4-6');
    $response->assertJsonPath('data.planning_mode', 'plan_then_act');
});

it('auto-generates a slug from the name', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/agents/quick-create", [
            'name' => 'Code Review Assistant',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.slug', 'code-review-assistant');
});

it('auto-enables the agent for the project', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/agents/quick-create", [
            'name' => 'Auto Enabled',
        ]);

    $response->assertCreated();

    $agentId = $response->json('data.id');
    $pivot = ProjectAgent::where('project_id', $this->project->id)
        ->where('agent_id', $agentId)
        ->first();

    expect($pivot)->not->toBeNull();
    expect($pivot->is_enabled)->toBeTrue();
});

it('accepts optional model and role', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/agents/quick-create", [
            'name' => 'Custom Agent',
            'model' => 'gpt-5.4',
            'role' => 'researcher',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.model', 'gpt-5.4');
    $response->assertJsonPath('data.role', 'researcher');
});

it('requires a name', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/agents/quick-create", []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['name']);
});
