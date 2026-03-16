<?php

use App\Models\Agent;
use App\Models\DelegationConfig;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::firstOrCreate(
        ['email' => 'test@test.com'],
        ['name' => 'Test User', 'password' => bcrypt('password')],
    );
    $this->project = Project::create(['name' => 'Test Project']);
    $this->sourceAgent = Agent::create(['name' => 'Source Agent', 'role' => 'planner', 'base_instructions' => '']);
    $this->targetAgent = Agent::create(['name' => 'Target Agent', 'role' => 'executor', 'base_instructions' => '']);
});

it('creates a delegation config via upsert', function () {
    $response = $this->actingAs($this->user)
        ->putJson("/api/projects/{$this->project->id}/delegation-configs", [
            'source_agent_id' => $this->sourceAgent->id,
            'target_agent_id' => $this->targetAgent->id,
            'trigger_condition' => 'when task requires code',
            'pass_conversation_history' => true,
            'pass_agent_memory' => true,
            'return_behavior' => 'report_back',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.source_agent_id', $this->sourceAgent->id);
    $response->assertJsonPath('data.target_agent_id', $this->targetAgent->id);
    $response->assertJsonPath('data.trigger_condition', 'when task requires code');
    $response->assertJsonPath('data.pass_agent_memory', true);
    $response->assertJsonPath('data.return_behavior', 'report_back');

    expect(DelegationConfig::count())->toBe(1);
});

it('lists delegation configs for a project', function () {
    DelegationConfig::create([
        'project_id' => $this->project->id,
        'source_agent_id' => $this->sourceAgent->id,
        'target_agent_id' => $this->targetAgent->id,
        'return_behavior' => 'fire_and_forget',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/projects/{$this->project->id}/delegation-configs");

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.return_behavior', 'fire_and_forget');
});

it('deletes a delegation config', function () {
    $config = DelegationConfig::create([
        'project_id' => $this->project->id,
        'source_agent_id' => $this->sourceAgent->id,
        'target_agent_id' => $this->targetAgent->id,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/delegation-configs/{$config->id}");

    $response->assertOk();
    expect(DelegationConfig::find($config->id))->toBeNull();
});

it('enforces uniqueness on source+target agent pair', function () {
    DelegationConfig::create([
        'project_id' => $this->project->id,
        'source_agent_id' => $this->sourceAgent->id,
        'target_agent_id' => $this->targetAgent->id,
        'return_behavior' => 'report_back',
    ]);

    // Upsert with same source+target should update, not duplicate
    $response = $this->actingAs($this->user)
        ->putJson("/api/projects/{$this->project->id}/delegation-configs", [
            'source_agent_id' => $this->sourceAgent->id,
            'target_agent_id' => $this->targetAgent->id,
            'return_behavior' => 'chain_forward',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.return_behavior', 'chain_forward');
    expect(DelegationConfig::count())->toBe(1);
});

it('requires at least one target', function () {
    $response = $this->actingAs($this->user)
        ->putJson("/api/projects/{$this->project->id}/delegation-configs", [
            'source_agent_id' => $this->sourceAgent->id,
        ]);

    $response->assertStatus(422);
});

it('includes delegation_configs in graph endpoint', function () {
    DelegationConfig::create([
        'project_id' => $this->project->id,
        'source_agent_id' => $this->sourceAgent->id,
        'target_agent_id' => $this->targetAgent->id,
        'trigger_condition' => 'always',
        'return_behavior' => 'report_back',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/projects/{$this->project->id}/graph");

    $response->assertOk();
    $response->assertJsonPath('data.delegation_configs.0.source_agent_id', $this->sourceAgent->id);
    $response->assertJsonPath('data.delegation_configs.0.target_agent_id', $this->targetAgent->id);
    $response->assertJsonPath('data.delegation_configs.0.trigger_condition', 'always');
});
