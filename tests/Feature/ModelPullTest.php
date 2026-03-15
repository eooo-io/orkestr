<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

// --- #409: Pull endpoint validation ---

test('pull endpoint requires model name', function () {
    $response = $this->postJson('/api/models/pull', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['model']);
});

test('pull endpoint rejects invalid model names', function () {
    $response = $this->postJson('/api/models/pull', ['model' => 'invalid model name!']);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['model']);
});

test('pull endpoint accepts valid model names', function () {
    // This will fail to connect to Ollama in test, but validates the model name
    $response = $this->post('/api/models/pull', ['model' => 'llama3:8b'], [
        'Accept' => 'text/event-stream',
    ]);

    // Should get 200 with SSE response (even if Ollama not reachable, it returns SSE error)
    $response->assertStatus(200);
    expect($response->headers->get('content-type'))->toStartWith('text/event-stream');
});

test('pull endpoint accepts model names with slashes and tags', function () {
    $response = $this->post('/api/models/pull', ['model' => 'library/model:latest'], [
        'Accept' => 'text/event-stream',
    ]);

    $response->assertStatus(200);
});

// --- #409: Delete endpoint ---

test('delete endpoint calls ollama api', function () {
    // Ollama not running in test environment, expect 502
    $response = $this->deleteJson('/api/models/codellama:latest');

    // Either 502 (not reachable) or 404 (model not found) — both are valid in test
    expect($response->status())->toBeIn([200, 404, 502]);
});

// --- #409: Pulling models list ---

test('pulling endpoint returns array', function () {
    $response = $this->getJson('/api/models/pulling');

    $response->assertStatus(200);
    $response->assertJsonStructure(['data']);
    expect($response->json('data'))->toBeArray();
});

// --- #411: Recommendations endpoint ---

test('recommendations endpoint returns expected structure', function () {
    $response = $this->getJson('/api/models/recommendations?task_type=chat');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'model',
                'provider',
                'reason',
                'size_gb',
                'local_available',
            ],
        ],
    ]);
});

test('recommendations endpoint rejects invalid task type', function () {
    $response = $this->getJson('/api/models/recommendations?task_type=invalid');

    $response->assertStatus(422);
    $response->assertJsonFragment(['error' => 'Invalid task type. Valid types: chat, code, summarization, translation, analysis, creative']);
});

test('recommendations for chat task type', function () {
    $response = $this->getJson('/api/models/recommendations?task_type=chat');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data)->not->toBeEmpty();

    // All items should have required fields
    foreach ($data as $rec) {
        expect($rec)->toHaveKeys(['model', 'provider', 'reason', 'size_gb', 'local_available']);
        expect($rec['local_available'])->toBeBool();
    }
});

test('recommendations for code task type', function () {
    $response = $this->getJson('/api/models/recommendations?task_type=code');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data)->not->toBeEmpty();

    // Should include code-specific models
    $models = array_column($data, 'model');
    expect($models)->toContain('codellama:latest');
});

test('recommendations for summarization task type', function () {
    $response = $this->getJson('/api/models/recommendations?task_type=summarization');

    $response->assertStatus(200);
    expect($response->json('data'))->not->toBeEmpty();
});

test('recommendations for translation task type', function () {
    $response = $this->getJson('/api/models/recommendations?task_type=translation');

    $response->assertStatus(200);
    expect($response->json('data'))->not->toBeEmpty();
});

test('recommendations for analysis task type', function () {
    $response = $this->getJson('/api/models/recommendations?task_type=analysis');

    $response->assertStatus(200);
    expect($response->json('data'))->not->toBeEmpty();
});

test('recommendations for creative task type', function () {
    $response = $this->getJson('/api/models/recommendations?task_type=creative');

    $response->assertStatus(200);
    expect($response->json('data'))->not->toBeEmpty();
});

test('recommendations defaults to chat when no task_type provided', function () {
    $response = $this->getJson('/api/models/recommendations');

    $response->assertStatus(200);
    expect($response->json('data'))->not->toBeEmpty();
});

test('recommendations local_available is false when ollama not running', function () {
    $response = $this->getJson('/api/models/recommendations?task_type=code');

    $response->assertStatus(200);
    $data = $response->json('data');

    // With no Ollama running, ollama models should show local_available = false
    $ollamaRecs = array_filter($data, fn ($r) => $r['provider'] === 'ollama');
    foreach ($ollamaRecs as $rec) {
        expect($rec['local_available'])->toBeFalse();
    }
});
