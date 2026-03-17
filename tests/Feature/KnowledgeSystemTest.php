<?php

use App\Models\Agent;
use App\Models\AgentKnowledge;
use App\Models\Project;
use App\Models\User;
use App\Http\Controllers\KnowledgeController;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create(['name' => 'Knowledge Test', 'path' => '/tmp/kb-test']);
    $this->agent = Agent::create([
        'name' => 'Knowledge Agent',
        'slug' => 'knowledge-agent',
        'role' => 'assistant',
        'base_instructions' => 'Test agent.',
        'knowledge_access' => true,
    ]);
});

// --- Knowledge API tests ---

test('store knowledge entry', function () {
    $response = $this->postJson(
        "/api/projects/{$this->project->id}/agents/{$this->agent->id}/knowledge",
        [
            'namespace' => 'facts',
            'key' => 'sky_color',
            'value' => ['statement' => 'The sky is blue', 'confidence' => 0.99],
        ]
    );

    $response->assertStatus(201);
    expect($response->json('data.namespace'))->toBe('facts');
    expect($response->json('data.key'))->toBe('sky_color');
});

test('store knowledge upserts on duplicate namespace+key', function () {
    // Create initial entry
    $this->postJson(
        "/api/projects/{$this->project->id}/agents/{$this->agent->id}/knowledge",
        [
            'namespace' => 'facts',
            'key' => 'color',
            'value' => ['statement' => 'Red'],
        ]
    );

    // Update same namespace+key
    $response = $this->postJson(
        "/api/projects/{$this->project->id}/agents/{$this->agent->id}/knowledge",
        [
            'namespace' => 'facts',
            'key' => 'color',
            'value' => ['statement' => 'Blue'],
        ]
    );

    $response->assertStatus(201);

    // Should only have one entry
    $count = AgentKnowledge::forAgent($this->agent->id, $this->project->id)
        ->where('namespace', 'facts')
        ->where('key', 'color')
        ->count();
    expect($count)->toBe(1);
});

test('query knowledge entries', function () {
    AgentKnowledge::create([
        'agent_id' => $this->agent->id,
        'project_id' => $this->project->id,
        'namespace' => 'facts',
        'key' => 'a',
        'value' => ['statement' => 'Fact A'],
    ]);
    AgentKnowledge::create([
        'agent_id' => $this->agent->id,
        'project_id' => $this->project->id,
        'namespace' => 'preferences',
        'key' => 'b',
        'value' => ['preference' => 'Dark mode', 'value' => true],
    ]);

    $response = $this->getJson("/api/projects/{$this->project->id}/agents/{$this->agent->id}/knowledge");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

test('query knowledge with namespace filter', function () {
    AgentKnowledge::create([
        'agent_id' => $this->agent->id,
        'project_id' => $this->project->id,
        'namespace' => 'facts',
        'key' => 'a',
        'value' => ['statement' => 'Fact A'],
    ]);
    AgentKnowledge::create([
        'agent_id' => $this->agent->id,
        'project_id' => $this->project->id,
        'namespace' => 'preferences',
        'key' => 'b',
        'value' => ['preference' => 'Light', 'value' => 'light'],
    ]);

    $response = $this->getJson("/api/projects/{$this->project->id}/agents/{$this->agent->id}/knowledge?namespace=facts");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.namespace'))->toBe('facts');
});

test('search knowledge returns relevant results', function () {
    AgentKnowledge::create([
        'agent_id' => $this->agent->id,
        'project_id' => $this->project->id,
        'namespace' => 'facts',
        'key' => 'sky',
        'value' => ['statement' => 'The sky is blue'],
    ]);
    AgentKnowledge::create([
        'agent_id' => $this->agent->id,
        'project_id' => $this->project->id,
        'namespace' => 'facts',
        'key' => 'grass',
        'value' => ['statement' => 'Grass is green'],
    ]);

    $response = $this->getJson("/api/projects/{$this->project->id}/agents/{$this->agent->id}/knowledge/search?q=sky");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.key'))->toBe('sky');
});

test('search knowledge matches across key and value', function () {
    AgentKnowledge::create([
        'agent_id' => $this->agent->id,
        'project_id' => $this->project->id,
        'namespace' => 'contacts',
        'key' => 'john_doe',
        'value' => ['name' => 'John Doe', 'role' => 'developer'],
    ]);

    // Search by key
    $response = $this->getJson("/api/projects/{$this->project->id}/agents/{$this->agent->id}/knowledge/search?q=john");
    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);

    // Search by value content
    $response = $this->getJson("/api/projects/{$this->project->id}/agents/{$this->agent->id}/knowledge/search?q=developer");
    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

test('delete knowledge entry', function () {
    $entry = AgentKnowledge::create([
        'agent_id' => $this->agent->id,
        'project_id' => $this->project->id,
        'namespace' => 'facts',
        'key' => 'to_delete',
        'value' => ['statement' => 'Delete me'],
    ]);

    $response = $this->deleteJson("/api/agent-knowledge/{$entry->id}");

    $response->assertStatus(204);
    expect(AgentKnowledge::find($entry->id))->toBeNull();
});

test('agent+project scoping isolates knowledge', function () {
    $otherAgent = Agent::create([
        'name' => 'Other Agent',
        'slug' => 'other-agent',
        'role' => 'assistant',
        'base_instructions' => 'Other.',
    ]);

    AgentKnowledge::create([
        'agent_id' => $this->agent->id,
        'project_id' => $this->project->id,
        'namespace' => 'facts',
        'key' => 'mine',
        'value' => ['statement' => 'My fact'],
    ]);
    AgentKnowledge::create([
        'agent_id' => $otherAgent->id,
        'project_id' => $this->project->id,
        'namespace' => 'facts',
        'key' => 'theirs',
        'value' => ['statement' => 'Their fact'],
    ]);

    $response = $this->getJson("/api/projects/{$this->project->id}/agents/{$this->agent->id}/knowledge");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.key'))->toBe('mine');
});

test('built-in namespace validation returns warnings', function () {
    // Missing required 'statement' field for 'facts' namespace
    $response = $this->postJson(
        "/api/projects/{$this->project->id}/agents/{$this->agent->id}/knowledge",
        [
            'namespace' => 'facts',
            'key' => 'bad_fact',
            'value' => ['some_other_field' => 'no statement here'],
        ]
    );

    // Should still succeed (soft validation)
    $response->assertStatus(201);
    // But should include warnings
    expect($response->json('warnings'))->not->toBeEmpty();
});

test('custom namespace allowed without warnings', function () {
    $response = $this->postJson(
        "/api/projects/{$this->project->id}/agents/{$this->agent->id}/knowledge",
        [
            'namespace' => 'my_custom_namespace',
            'key' => 'anything',
            'value' => ['whatever' => 'I want'],
        ]
    );

    $response->assertStatus(201);
    expect($response->json('warnings'))->toBeNull();
});

test('builtin namespaces constant exists', function () {
    $namespaces = KnowledgeController::BUILTIN_NAMESPACES;

    expect($namespaces)->toHaveKeys(['facts', 'preferences', 'patterns', 'contacts', 'history']);
    foreach ($namespaces as $ns) {
        expect($ns)->toHaveKeys(['description', 'schema']);
    }
});
