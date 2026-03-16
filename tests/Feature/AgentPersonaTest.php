<?php

use App\Models\Agent;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\AgentComposeService;
use App\Services\SkillCompositionService;
use App\Services\TemplateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->org = Organization::create([
        'name' => 'Persona Org',
        'slug' => 'persona-org',
        'plan' => 'pro',
    ]);
    $this->org->users()->attach($this->user->id, ['role' => 'owner']);
    $this->user->update(['current_organization_id' => $this->org->id]);
    app()->instance('current_organization', $this->org);
});

test('agent can be created with persona', function () {
    $agent = Agent::create([
        'name' => 'Research Agent',
        'slug' => 'research-agent',
        'role' => 'researcher',
        'base_instructions' => 'Research topics thoroughly.',
        'persona' => [
            'name' => 'Aria',
            'aliases' => ['aria', 'research-lead'],
            'avatar' => '🔬',
            'personality' => 'concise and analytical',
            'bio' => 'Senior research analyst specializing in data synthesis',
        ],
    ]);

    expect($agent->persona)->toBeArray();
    expect($agent->personaName())->toBe('Aria');
    expect($agent->personaAvatar())->toBe('🔬');
    expect($agent->personaAliases())->toBe(['aria', 'research-lead']);
    expect($agent->personaPersonality())->toBe('concise and analytical');
    expect($agent->personaBio())->toBe('Senior research analyst specializing in data synthesis');
});

test('agent displayName returns persona name when set', function () {
    $agent = Agent::create([
        'name' => 'Research Agent',
        'slug' => 'research-agent-2',
        'role' => 'researcher',
        'base_instructions' => '',
        'persona' => ['name' => 'Aria'],
    ]);

    expect($agent->displayName())->toBe('Aria');
});

test('agent displayName falls back to name when no persona', function () {
    $agent = Agent::create([
        'name' => 'Research Agent',
        'slug' => 'research-agent-3',
        'role' => 'researcher',
        'base_instructions' => '',
    ]);

    expect($agent->displayName())->toBe('Research Agent');
});

test('agent personaContext builds correct prompt', function () {
    $agent = Agent::create([
        'name' => 'Writer Agent',
        'slug' => 'writer-agent',
        'role' => 'writer',
        'persona' => [
            'name' => 'Echo',
            'bio' => 'Technical writer with deep engineering knowledge.',
            'personality' => 'friendly and precise',
            'aliases' => ['echo', 'tech-writer'],
        ],
    ]);

    $context = $agent->personaContext();
    expect($context)->toContain('You are Echo.');
    expect($context)->toContain('Technical writer with deep engineering knowledge.');
    expect($context)->toContain('friendly and precise style');
    expect($context)->toContain('echo, tech-writer');
});

test('agent personaContext returns null when no persona', function () {
    $agent = Agent::create([
        'name' => 'Basic Agent',
        'slug' => 'basic-agent',
        'role' => 'general',
    ]);

    expect($agent->personaContext())->toBeNull();
});

test('agent persona is included in API response', function () {
    Agent::create([
        'name' => 'API Agent',
        'slug' => 'api-agent',
        'role' => 'api',
        'persona' => [
            'name' => 'Nova',
            'avatar' => '🌟',
        ],
    ]);

    $response = $this->getJson('/api/agents');
    $response->assertOk();

    $agent = collect($response->json('data'))->firstWhere('slug', 'api-agent');
    expect($agent['persona']['name'])->toBe('Nova');
    expect($agent['persona']['avatar'])->toBe('🌟');
});

test('agent persona can be updated via API', function () {
    $agent = Agent::create([
        'name' => 'Update Agent',
        'slug' => 'update-agent',
        'role' => 'updater',
    ]);

    $response = $this->putJson("/api/agents/{$agent->id}", [
        'persona' => [
            'name' => 'Atlas',
            'personality' => 'methodical',
        ],
    ]);

    $response->assertOk();
    $agent->refresh();
    expect($agent->personaName())->toBe('Atlas');
    expect($agent->personaPersonality())->toBe('methodical');
});

test('compose includes persona context in output', function () {
    $project = Project::create([
        'name' => 'Persona Project',
        'path' => '/tmp/persona-test',
    ]);

    $agent = Agent::create([
        'name' => 'Compose Agent',
        'slug' => 'compose-agent',
        'role' => 'composer',
        'base_instructions' => 'Do your job well.',
        'persona' => [
            'name' => 'Sage',
            'bio' => 'Wise advisor.',
            'personality' => 'thoughtful',
        ],
    ]);

    $project->agents()->attach($agent->id, ['is_enabled' => true]);

    $composer = new AgentComposeService(
        new SkillCompositionService(),
        new TemplateResolver(),
    );

    $result = $composer->compose($project, $agent);

    expect($result['content'])->toContain('# Sage');
    expect($result['content'])->toContain('You are Sage.');
    expect($result['content'])->toContain('Wise advisor.');
    expect($result['content'])->toContain('thoughtful style');
    expect($result['agent']['persona']['name'])->toBe('Sage');
    expect($result['agent']['display_name'])->toBe('Sage');
});

test('graph API includes persona data for agents', function () {
    $project = Project::create([
        'name' => 'Graph Persona Project',
        'path' => '/tmp/graph-persona-test',
    ]);

    $agent = Agent::create([
        'name' => 'Graph Agent',
        'slug' => 'graph-agent',
        'role' => 'grapher',
        'persona' => [
            'name' => 'Pixel',
            'avatar' => '🎨',
        ],
    ]);

    $project->agents()->attach($agent->id, ['is_enabled' => true]);

    $response = $this->getJson("/api/projects/{$project->id}/graph");
    $response->assertOk();

    $agents = $response->json('data.agents');
    $graphAgent = collect($agents)->firstWhere('slug', 'graph-agent');
    expect($graphAgent['persona']['name'])->toBe('Pixel');
    expect($graphAgent['display_name'])->toBe('Pixel');
});
