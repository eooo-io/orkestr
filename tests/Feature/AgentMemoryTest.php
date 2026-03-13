<?php

use App\Models\Agent;
use App\Models\AgentConversation;
use App\Models\AgentMemory;
use App\Models\Project;
use App\Models\User;
use App\Services\Execution\AgentMemoryService;
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

// --- AgentMemory model tests ---

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

// --- AgentMemoryService tests ---

test('AgentMemoryService stores and retrieves memories', function () {
    $service = app(AgentMemoryService::class);

    $service->store($this->agent, $this->project, 'long_term', ['fact' => 'The sky is blue'], 'sky_color');
    $service->store($this->agent, $this->project, 'working', ['temp' => 'data'], 'temp_key');

    $longTerm = $service->retrieve($this->agent, $this->project, 'long_term');
    expect($longTerm)->toHaveCount(1);
    expect($longTerm->first()->key)->toBe('sky_color');

    $all = $service->retrieve($this->agent, $this->project);
    expect($all)->toHaveCount(2);
});

test('AgentMemoryService clears memories by type', function () {
    $service = app(AgentMemoryService::class);

    $service->store($this->agent, $this->project, 'working', 'data1');
    $service->store($this->agent, $this->project, 'working', 'data2');
    $service->store($this->agent, $this->project, 'long_term', 'keep');

    $deleted = $service->clearType($this->agent, $this->project, 'working');
    expect($deleted)->toBe(2);

    $remaining = $service->retrieve($this->agent, $this->project);
    expect($remaining)->toHaveCount(1);
    expect($remaining->first()->type)->toBe('long_term');
});

test('AgentMemoryService saves and retrieves conversations', function () {
    $service = app(AgentMemoryService::class);

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

test('AgentMemoryService assembles context within token budget', function () {
    $service = app(AgentMemoryService::class);

    // Add some long-term memories
    for ($i = 0; $i < 5; $i++) {
        $service->store($this->agent, $this->project, 'long_term', "Fact number {$i}", "fact_{$i}");
    }

    $context = $service->assembleContext($this->agent, $this->project, 'recent', 4000);

    expect($context['memories'])->not->toBeEmpty();
    expect($context['token_estimate'])->toBeLessThanOrEqual(4000);
});

// --- API tests ---

test('memories list endpoint works', function () {
    AgentMemory::create([
        'agent_id' => $this->agent->id,
        'project_id' => $this->project->id,
        'type' => 'long_term',
        'content' => ['value' => 'test'],
    ]);

    $response = $this->getJson("/api/projects/{$this->project->id}/agents/{$this->agent->id}/memories");

    $response->assertOk();
    expect($response->json('memories'))->toHaveCount(1);
});

test('memories store endpoint creates memory', function () {
    $response = $this->postJson("/api/projects/{$this->project->id}/agents/{$this->agent->id}/memories", [
        'type' => 'long_term',
        'key' => 'test_key',
        'content' => ['value' => 'test_value'],
    ]);

    $response->assertStatus(201);
    expect($response->json('memory.key'))->toBe('test_key');
});

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

test('memory delete endpoint works', function () {
    $memory = AgentMemory::create([
        'agent_id' => $this->agent->id,
        'project_id' => $this->project->id,
        'type' => 'long_term',
        'content' => ['value' => 'delete me'],
    ]);

    $response = $this->deleteJson("/api/memories/{$memory->id}");

    $response->assertStatus(204);
    expect(AgentMemory::find($memory->id))->toBeNull();
});
