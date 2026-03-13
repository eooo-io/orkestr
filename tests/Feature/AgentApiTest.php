<?php

use App\Models\Agent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::firstOrCreate(
        ['email' => 'test@test.com'],
        ['name' => 'Test User', 'password' => bcrypt('password')],
    );
});

it('lists all agents', function () {
    Agent::create(['name' => 'Agent A', 'role' => 'a', 'base_instructions' => 'A']);
    Agent::create(['name' => 'Agent B', 'role' => 'b', 'base_instructions' => 'B']);

    $response = $this->actingAs($this->user)
        ->getJson('/api/agents');

    $response->assertOk();
    $response->assertJsonStructure(['data' => [['id', 'name', 'role', 'has_loop_config']]]);
});

it('creates an agent', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/agents', [
            'name' => 'New Agent',
            'role' => 'creator',
            'base_instructions' => 'Create things.',
            'objective_template' => 'Create the thing',
            'max_iterations' => 10,
            'planning_mode' => 'plan_then_act',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'New Agent');
    $response->assertJsonPath('data.planning_mode', 'plan_then_act');
    $response->assertJsonPath('data.max_iterations', 10);
});

it('shows a single agent', function () {
    $agent = Agent::create(['name' => 'Show Me', 'role' => 'show', 'base_instructions' => 'Show']);

    $response = $this->actingAs($this->user)
        ->getJson("/api/agents/{$agent->id}");

    $response->assertOk();
    $response->assertJsonPath('data.name', 'Show Me');
    $response->assertJsonPath('data.has_loop_config', false);
});

it('updates an agent', function () {
    $agent = Agent::create(['name' => 'Old Name', 'role' => 'old', 'base_instructions' => 'Old']);

    $response = $this->actingAs($this->user)
        ->putJson("/api/agents/{$agent->id}", [
            'name' => 'New Name',
            'planning_mode' => 'react',
            'can_delegate' => true,
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.name', 'New Name');
    $response->assertJsonPath('data.planning_mode', 'react');
    $response->assertJsonPath('data.can_delegate', true);
});

it('deletes an agent', function () {
    $agent = Agent::create(['name' => 'Delete Me', 'role' => 'delete', 'base_instructions' => 'Delete']);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/agents/{$agent->id}");

    $response->assertOk();
    expect(Agent::find($agent->id))->toBeNull();
});

it('duplicates an agent', function () {
    $agent = Agent::create([
        'name' => 'Original',
        'role' => 'original',
        'base_instructions' => 'Original instructions',
        'planning_mode' => 'react',
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/agents/{$agent->id}/duplicate");

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Original (Copy)');
    $response->assertJsonPath('data.planning_mode', 'react');
    expect(Agent::count())->toBeGreaterThanOrEqual(2);
});

it('exports agent as JSON', function () {
    $agent = Agent::create([
        'name' => 'Export Me',
        'role' => 'exporter',
        'base_instructions' => 'Export',
        'objective_template' => 'Export the thing',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/agents/{$agent->id}/export?format=json");

    $response->assertOk();
    $response->assertJsonPath('format', 'json');
    $response->assertJsonPath('content.name', 'Export Me');
    $response->assertJsonPath('content.objective_template', 'Export the thing');
});

it('exports agent as YAML', function () {
    $agent = Agent::create([
        'name' => 'Yaml Agent',
        'role' => 'yaml',
        'base_instructions' => 'Yaml',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/agents/{$agent->id}/export?format=yaml");

    $response->assertOk();
    $response->assertJsonPath('format', 'yaml');
    expect($response->json('content'))->toContain('name: ');
});

it('validates required fields on create', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/agents', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['name', 'role']);
});

it('validates planning_mode enum on create', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/agents', [
            'name' => 'Invalid',
            'role' => 'invalid',
            'planning_mode' => 'invalid_mode',
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['planning_mode']);
});
