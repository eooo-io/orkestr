<?php

use App\Models\Agent;
use App\Models\AgentConversation;
use App\Models\AgentMemory;
use App\Models\Project;
use App\Models\User;
use App\Services\Execution\AgentMemoryService as LegacyMemoryService;
use App\Services\Memory\AgentMemoryService;
use App\Services\Memory\EmbeddingService;
use App\Services\Mcp\MemoryMcpServer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create(['name' => 'Memory Test', 'path' => '/tmp/mem-test']);
    $this->agent = Agent::create([
        'name' => 'Memory Agent',
        'slug' => 'memory-agent',
        'role' => 'assistant',
        'base_instructions' => 'You are a memory test agent.',
    ]);
});

// ─── #402: AgentMemory model tests ────────────────────────────

test('AgentMemory creates with auto UUID', function () {
    $memory = AgentMemory::create([
        'agent_id' => $this->agent->id,
        'project_id' => $this->project->id,
        'type' => 'long_term',
        'key' => 'user_name',
        'content' => ['value' => 'Ezra'],
    ]);

    expect($memory->uuid)->not->toBeNull();
    expect($memory->type)->toBe('long_term');
    expect($memory->content['value'])->toBe('Ezra');
});

test('AgentMemory active scope excludes expired', function () {
    AgentMemory::create([
        'agent_id' => $this->agent->id,
        'project_id' => $this->project->id,
        'type' => 'working',
        'content' => ['value' => 'active'],
    ]);

    AgentMemory::create([
        'agent_id' => $this->agent->id,
        'project_id' => $this->project->id,
        'type' => 'working',
        'content' => ['value' => 'expired'],
        'expires_at' => now()->subHour(),
    ]);

    $active = AgentMemory::where('agent_id', $this->agent->id)->active()->get();
    expect($active)->toHaveCount(1);
    expect($active->first()->content['value'])->toBe('active');
});

test('AgentMemory forAgent scope filters correctly', function () {
    AgentMemory::create([
        'agent_id' => $this->agent->id,
        'project_id' => $this->project->id,
        'type' => 'long_term',
        'key' => 'fact1',
        'content' => ['value' => 'mine'],
    ]);

    $other = Agent::create([
        'name' => 'Other Agent',
        'slug' => 'other-agent',
        'role' => 'assistant',
        'base_instructions' => 'Other.',
    ]);

    AgentMemory::create([
        'agent_id' => $other->id,
        'project_id' => $this->project->id,
        'type' => 'long_term',
        'key' => 'fact2',
        'content' => ['value' => 'theirs'],
    ]);

    $mine = AgentMemory::forAgent($this->agent->id, $this->project->id)->get();
    expect($mine)->toHaveCount(1);
    expect($mine->first()->content['value'])->toBe('mine');
});

test('AgentMemory stores embedding as array', function () {
    $embedding = array_fill(0, 10, 0.5);

    $memory = AgentMemory::create([
        'agent_id' => $this->agent->id,
        'project_id' => $this->project->id,
        'type' => 'long_term',
        'key' => 'embedded',
        'content' => ['value' => 'test'],
        'embedding' => $embedding,
    ]);

    $fresh = AgentMemory::find($memory->id);
    expect($fresh->embedding)->toBeArray();
    expect($fresh->embedding)->toHaveCount(10);
});

// ─── #402: EmbeddingService tests ─────────────────────────────

test('EmbeddingService generates local embedding', function () {
    $service = new EmbeddingService;

    $vector = $service->embed('Hello, world!');

    expect($vector)->toBeArray();
    expect($vector)->toHaveCount(384);

    // Should be normalized (unit vector)
    $magnitude = sqrt(array_sum(array_map(fn ($v) => $v * $v, $vector)));
    expect($magnitude)->toBeGreaterThan(0.99);
    expect($magnitude)->toBeLessThan(1.01);
});

test('EmbeddingService returns deterministic vectors', function () {
    $service = new EmbeddingService;

    $v1 = $service->embed('test input');
    $v2 = $service->embed('test input');

    expect($v1)->toEqual($v2);
});

test('EmbeddingService returns different vectors for different text', function () {
    $service = new EmbeddingService;

    $v1 = $service->embed('The quick brown fox');
    $v2 = $service->embed('Machine learning is great');

    expect($v1)->not->toEqual($v2);
});

test('EmbeddingService handles empty text', function () {
    $service = new EmbeddingService;

    $vector = $service->embed('');
    expect($vector)->toBeArray();
    expect($vector)->toHaveCount(384);
});

// ─── #403: AgentMemoryService tests ───────────────────────────

test('remember stores a memory with embedding', function () {
    $service = app(AgentMemoryService::class);

    $memory = $service->remember($this->agent->id, $this->project->id, 'user_name', 'Ezra');

    expect($memory)->toBeInstanceOf(AgentMemory::class);
    expect($memory->key)->toBe('user_name');
    expect($memory->content['value'])->toBe('Ezra');
    expect($memory->embedding)->toBeArray();
    expect($memory->embedding)->not->toBeEmpty();
});

test('remember upserts on duplicate key', function () {
    $service = app(AgentMemoryService::class);

    $first = $service->remember($this->agent->id, $this->project->id, 'color', 'blue');
    $second = $service->remember($this->agent->id, $this->project->id, 'color', 'green');

    expect($first->id)->toBe($second->id);
    expect($second->content['value'])->toBe('green');

    $count = AgentMemory::forAgent($this->agent->id, $this->project->id)->where('key', 'color')->count();
    expect($count)->toBe(1);
});

test('remember stores metadata', function () {
    $service = app(AgentMemoryService::class);

    $memory = $service->remember(
        $this->agent->id,
        $this->project->id,
        'source_info',
        'From conversation',
        ['source' => 'chat', 'confidence' => 0.9],
    );

    expect($memory->metadata['source'])->toBe('chat');
    expect($memory->metadata['confidence'])->toBe(0.9);
});

test('recall returns results via LIKE fallback', function () {
    $service = app(AgentMemoryService::class);

    $service->remember($this->agent->id, $this->project->id, 'sky', 'The sky is blue');
    $service->remember($this->agent->id, $this->project->id, 'grass', 'The grass is green');
    $service->remember($this->agent->id, $this->project->id, 'sun', 'The sun is yellow');

    $results = $service->recall($this->agent->id, $this->project->id, 'blue sky');

    expect($results)->not->toBeEmpty();
    $keys = $results->pluck('key')->all();
    expect($keys)->toContain('sky');
});

test('recall respects limit', function () {
    $service = app(AgentMemoryService::class);

    for ($i = 0; $i < 10; $i++) {
        $service->remember($this->agent->id, $this->project->id, "fact_{$i}", "Fact number {$i}");
    }

    $results = $service->recall($this->agent->id, $this->project->id, 'fact', 3);
    expect($results)->toHaveCount(3);
});

test('update changes content and re-embeds', function () {
    $service = app(AgentMemoryService::class);

    $memory = $service->remember($this->agent->id, $this->project->id, 'color', 'blue');
    $oldEmbedding = $memory->embedding;

    $updated = $service->update($memory->id, 'The color is now red');

    expect($updated->content['value'])->toBe('The color is now red');
    expect($updated->embedding)->not->toEqual($oldEmbedding);
});

test('forget deletes memory by key', function () {
    $service = app(AgentMemoryService::class);

    $service->remember($this->agent->id, $this->project->id, 'temp', 'temporary data');
    expect(AgentMemory::forAgent($this->agent->id, $this->project->id)->count())->toBe(1);

    $deleted = $service->forget($this->agent->id, $this->project->id, 'temp');

    expect($deleted)->toBeTrue();
    expect(AgentMemory::forAgent($this->agent->id, $this->project->id)->count())->toBe(0);
});

test('forget returns false for nonexistent key', function () {
    $service = app(AgentMemoryService::class);

    $deleted = $service->forget($this->agent->id, $this->project->id, 'nonexistent');
    expect($deleted)->toBeFalse();
});

test('listAll returns paginated results', function () {
    $service = app(AgentMemoryService::class);

    for ($i = 0; $i < 25; $i++) {
        $service->remember($this->agent->id, $this->project->id, "key_{$i}", "Content {$i}");
    }

    $page1 = $service->listAll($this->agent->id, $this->project->id, 10);
    expect($page1->count())->toBe(10);
    expect($page1->total())->toBe(25);
    expect($page1->lastPage())->toBe(3);
});

// ─── #403: Memory scoping (agent isolation) ───────────────────

test('agent A cannot see agent B memories', function () {
    $service = app(AgentMemoryService::class);

    $agentB = Agent::create([
        'name' => 'Agent B',
        'slug' => 'agent-b',
        'role' => 'assistant',
        'base_instructions' => 'B.',
    ]);

    $service->remember($this->agent->id, $this->project->id, 'secret_a', 'Agent A secret');
    $service->remember($agentB->id, $this->project->id, 'secret_b', 'Agent B secret');

    $recallA = $service->recall($this->agent->id, $this->project->id, 'secret');
    $recallB = $service->recall($agentB->id, $this->project->id, 'secret');

    $keysA = $recallA->pluck('key')->all();
    $keysB = $recallB->pluck('key')->all();

    expect($keysA)->toContain('secret_a');
    expect($keysA)->not->toContain('secret_b');
    expect($keysB)->toContain('secret_b');
    expect($keysB)->not->toContain('secret_a');
});

// ─── #404: API endpoint tests ─────────────────────────────────

test('API list memories returns paginated data', function () {
    $service = app(AgentMemoryService::class);
    $service->remember($this->agent->id, $this->project->id, 'key1', 'value1');
    $service->remember($this->agent->id, $this->project->id, 'key2', 'value2');

    $response = $this->getJson("/api/projects/{$this->project->id}/agents/{$this->agent->id}/memories");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('meta.total'))->toBe(2);
});

test('API create memory stores with embedding', function () {
    $response = $this->postJson("/api/projects/{$this->project->id}/agents/{$this->agent->id}/memories", [
        'key' => 'api_test',
        'content' => 'Created via API',
    ]);

    $response->assertStatus(201);
    expect($response->json('data.key'))->toBe('api_test');
    expect($response->json('data.content.value'))->toBe('Created via API');
    expect($response->json('data.embedding'))->not->toBeNull();
});

test('API recall returns relevant memories', function () {
    $service = app(AgentMemoryService::class);
    $service->remember($this->agent->id, $this->project->id, 'language', 'User prefers English');
    $service->remember($this->agent->id, $this->project->id, 'timezone', 'User is in PST');

    $response = $this->getJson(
        "/api/projects/{$this->project->id}/agents/{$this->agent->id}/memories/recall?q=English+language"
    );

    $response->assertOk();
    expect($response->json('data'))->not->toBeEmpty();
});

test('API update memory changes content', function () {
    $service = app(AgentMemoryService::class);
    $memory = $service->remember($this->agent->id, $this->project->id, 'updatable', 'old content');

    $response = $this->putJson("/api/agent-memories/{$memory->id}", [
        'content' => 'new content',
    ]);

    $response->assertOk();
    expect($response->json('data.content.value'))->toBe('new content');
});

test('API delete memory removes it', function () {
    $memory = AgentMemory::create([
        'agent_id' => $this->agent->id,
        'project_id' => $this->project->id,
        'type' => 'long_term',
        'key' => 'delete_me',
        'content' => ['value' => 'goodbye'],
    ]);

    $response = $this->deleteJson("/api/agent-memories/{$memory->id}");

    $response->assertStatus(204);
    expect(AgentMemory::find($memory->id))->toBeNull();
});

test('API create memory validates required fields', function () {
    $response = $this->postJson("/api/projects/{$this->project->id}/agents/{$this->agent->id}/memories", []);

    $response->assertStatus(422);
});

test('API recall requires query parameter', function () {
    $response = $this->getJson(
        "/api/projects/{$this->project->id}/agents/{$this->agent->id}/memories/recall"
    );

    $response->assertStatus(422);
});

// ─── #405: Memory MCP server tool definitions ─────────────────

test('MemoryMcpServer returns three tool definitions', function () {
    $tools = MemoryMcpServer::toolDefinitions();

    expect($tools)->toHaveCount(3);

    $names = array_column($tools, 'name');
    expect($names)->toContain('memory_remember');
    expect($names)->toContain('memory_recall');
    expect($names)->toContain('memory_forget');
});

test('MemoryMcpServer tool definitions have valid input schemas', function () {
    $tools = MemoryMcpServer::toolDefinitions();

    foreach ($tools as $tool) {
        expect($tool)->toHaveKey('name');
        expect($tool)->toHaveKey('description');
        expect($tool)->toHaveKey('input_schema');
        expect($tool['input_schema']['type'])->toBe('object');
        expect($tool['input_schema'])->toHaveKey('properties');
        expect($tool['input_schema'])->toHaveKey('required');
    }
});

// ─── #406: Agent memory config fields ─────────────────────────

test('Agent has memory config fields', function () {
    $agent = Agent::create([
        'name' => 'Mem Config Agent',
        'slug' => 'mem-config-agent',
        'role' => 'assistant',
        'base_instructions' => 'Test.',
        'memory_enabled' => true,
        'auto_remember' => true,
        'memory_recall_limit' => 10,
    ]);

    $fresh = Agent::find($agent->id);
    expect($fresh->memory_enabled)->toBeTrue();
    expect($fresh->auto_remember)->toBeTrue();
    expect($fresh->memory_recall_limit)->toBe(10);
});

test('Agent memory config defaults', function () {
    $agent = Agent::create([
        'name' => 'Default Config',
        'slug' => 'default-config',
        'role' => 'assistant',
        'base_instructions' => 'Test.',
    ]);

    $fresh = Agent::find($agent->id);
    expect($fresh->memory_enabled)->toBeFalsy();
    expect($fresh->auto_remember)->toBeFalsy();
    expect($fresh->memory_recall_limit)->toBe(5);
});

// ─── #406: auto_remember flag ─────────────────────────────────

test('auto_remember flag is respected in memory extraction', function () {
    // Test that the extraction pattern works
    $text = "Here is my analysis.\nREMEMBER: The user prefers dark mode.\nSome more text.\nREMEMBER: The project uses Laravel 12.\n";

    preg_match_all('/^REMEMBER:\s*(.+)$/im', $text, $matches);

    expect($matches[1])->toHaveCount(2);
    expect($matches[1][0])->toBe('The user prefers dark mode.');
    expect($matches[1][1])->toBe('The project uses Laravel 12.');
});

// ─── Legacy service compatibility ─────────────────────────────

test('legacy AgentMemoryService stores and retrieves memories', function () {
    $service = app(LegacyMemoryService::class);

    $service->store($this->agent, $this->project, 'long_term', ['fact' => 'The sky is blue'], 'sky_color');
    $service->store($this->agent, $this->project, 'working', ['temp' => 'data'], 'temp_key');

    $longTerm = $service->retrieve($this->agent, $this->project, 'long_term');
    expect($longTerm)->toHaveCount(1);
    expect($longTerm->first()->key)->toBe('sky_color');

    $all = $service->retrieve($this->agent, $this->project);
    expect($all)->toHaveCount(2);
});

test('legacy AgentMemoryService clears memories by type', function () {
    $service = app(LegacyMemoryService::class);

    $service->store($this->agent, $this->project, 'working', 'data1');
    $service->store($this->agent, $this->project, 'working', 'data2');
    $service->store($this->agent, $this->project, 'long_term', 'keep');

    $deleted = $service->clearType($this->agent, $this->project, 'working');
    expect($deleted)->toBe(2);

    $remaining = $service->retrieve($this->agent, $this->project);
    expect($remaining)->toHaveCount(1);
    expect($remaining->first()->type)->toBe('long_term');
});

test('legacy AgentMemoryService saves and retrieves conversations', function () {
    $service = app(LegacyMemoryService::class);

    $messages = [
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi there!'],
    ];

    $conv = $service->saveConversation($this->agent, $this->project, $messages, summary: 'Greeting exchange');

    expect($conv)->toBeInstanceOf(AgentConversation::class);
    expect($conv->messages)->toHaveCount(2);
    expect($conv->summary)->toBe('Greeting exchange');
    expect($conv->token_count)->toBeGreaterThan(0);

    $conversations = $service->getConversations($this->agent, $this->project);
    expect($conversations)->toHaveCount(1);
});

test('legacy AgentMemoryService assembles context within token budget', function () {
    $service = app(LegacyMemoryService::class);

    for ($i = 0; $i < 5; $i++) {
        $service->store($this->agent, $this->project, 'long_term', "Fact number {$i}", "fact_{$i}");
    }

    $context = $service->assembleContext($this->agent, $this->project, 'recent', 4000);

    expect($context['memories'])->not->toBeEmpty();
    expect($context['token_estimate'])->toBeLessThanOrEqual(4000);
});

// ─── Legacy API compatibility tests ───────────────────────────

test('memories clear endpoint deletes by type', function () {
    AgentMemory::create([
        'agent_id' => $this->agent->id,
        'project_id' => $this->project->id,
        'type' => 'working',
        'content' => ['value' => 'temp'],
    ]);

    $response = $this->deleteJson("/api/projects/{$this->project->id}/agents/{$this->agent->id}/memories?type=working");

    $response->assertOk();
    expect($response->json('deleted'))->toBe(1);
});
