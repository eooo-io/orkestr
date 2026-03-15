<?php

use App\Models\AppSetting;
use App\Models\CustomEndpoint;
use App\Models\Organization;
use App\Models\User;
use App\Services\Execution\Guards\NetworkGuard;
use App\Services\LLM\AirGapService;
use App\Services\LLM\GrokProvider;
use App\Services\LLM\LLMProviderFactory;
use App\Services\LLM\LocalModelBrowserService;
use App\Services\LLM\ModelComparisonService;
use App\Services\LLM\ModelHealthCheckService;
use App\Services\LLM\OpenAICompatibleProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->org = Organization::create([
        'name' => 'Test Org',
        'slug' => 'test-org-e4',
        'plan' => 'teams',
    ]);
    $this->org->users()->attach($this->user->id, ['role' => 'owner']);
    $this->user->update(['current_organization_id' => $this->org->id]);
    app()->instance('current_organization', $this->org);
});

// --- #252 Grok/xAI Provider ---

test('GrokProvider implements LLMProviderInterface', function () {
    $provider = new GrokProvider();
    expect($provider)->toBeInstanceOf(\App\Services\LLM\LLMProviderInterface::class);
});

test('GrokProvider returns Grok model list', function () {
    $provider = new GrokProvider();
    $models = $provider->models();

    expect($models)->toContain('grok-3');
    expect($models)->toContain('grok-3-fast');
    expect($models)->toContain('grok-3-mini');
    expect($models)->toContain('grok-3-mini-fast');
});

test('GrokProvider throws when API key not configured', function () {
    $provider = new GrokProvider();
    $gen = $provider->stream('Hello', [['role' => 'user', 'content' => 'Hi']], 'grok-3', 100);
    // Consume the generator to trigger the exception
    iterator_to_array($gen);
})->throws(\RuntimeException::class, 'Grok/xAI API key not configured');

test('GrokProvider chat throws not implemented', function () {
    $provider = new GrokProvider();
    $provider->chat('Hello', [], 'grok-3', 100);
})->throws(\RuntimeException::class, 'not yet implemented');

test('LLMProviderFactory routes grok- prefix to GrokProvider', function () {
    $factory = new LLMProviderFactory();
    $provider = $factory->make('grok-3');

    expect($provider)->toBeInstanceOf(GrokProvider::class);
});

test('LLMProviderFactory identifies grok provider name', function () {
    $factory = new LLMProviderFactory();
    expect($factory->providerName('grok-3'))->toBe('grok');
    expect($factory->providerName('grok-3-mini'))->toBe('grok');
});

test('LLMProviderFactory availableModels includes Grok section', function () {
    $factory = new LLMProviderFactory();
    $models = $factory->availableModels();

    $grokSection = collect($models)->firstWhere('provider', 'grok');
    expect($grokSection)->not->toBeNull();
    expect($grokSection['label'])->toBe('Grok (xAI)');
});

// --- #253 Generic OpenAI-compatible endpoint ---

test('OpenAICompatibleProvider implements LLMProviderInterface', function () {
    $provider = new OpenAICompatibleProvider(
        baseUrl: 'http://localhost:8080/v1',
        apiKey: 'test-key',
        providerName: 'Test LM',
    );

    expect($provider)->toBeInstanceOf(\App\Services\LLM\LLMProviderInterface::class);
});

test('OpenAICompatibleProvider chat throws not implemented', function () {
    $provider = new OpenAICompatibleProvider(
        baseUrl: 'http://localhost:8080/v1',
    );
    $provider->chat('Hi', [], 'model', 100);
})->throws(\RuntimeException::class, 'not yet implemented');

test('OpenAICompatibleProvider cleans custom model prefix', function () {
    // Use reflection to test the cleanModelName method
    $provider = new OpenAICompatibleProvider(baseUrl: 'http://localhost:8080/v1');
    $reflection = new \ReflectionMethod($provider, 'cleanModelName');

    expect($reflection->invoke($provider, 'custom:my-server:llama3'))->toBe('llama3');
    expect($reflection->invoke($provider, 'custom:slug'))->toBe('slug');
    expect($reflection->invoke($provider, 'plain-model'))->toBe('plain-model');
});

test('CustomEndpoint model creates with auto-slug', function () {
    $ep = CustomEndpoint::create([
        'name' => 'My vLLM Server',
        'base_url' => 'http://localhost:8000/v1',
        'organization_id' => $this->org->id,
    ]);

    expect($ep->slug)->toBe('my-vllm-server');
    $ep->refresh();
    expect($ep->enabled)->toBeTrue();
    expect($ep->health_status)->toBe('unknown');
});

test('CustomEndpoint CRUD via API', function () {
    // Create
    $response = $this->postJson('/api/custom-endpoints', [
        'name' => 'LM Studio',
        'base_url' => 'http://localhost:1234/v1',
        'models' => ['llama3', 'codellama'],
    ]);
    $response->assertStatus(201);
    $id = $response->json('data.id');

    // List
    $response = $this->getJson('/api/custom-endpoints');
    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);

    // Show
    $response = $this->getJson("/api/custom-endpoints/{$id}");
    $response->assertOk();
    expect($response->json('data.name'))->toBe('LM Studio');

    // Update
    $response = $this->putJson("/api/custom-endpoints/{$id}", [
        'name' => 'LM Studio Updated',
        'enabled' => false,
    ]);
    $response->assertOk();
    expect($response->json('data.name'))->toBe('LM Studio Updated');
    expect($response->json('data.enabled'))->toBeFalse();

    // Delete
    $response = $this->deleteJson("/api/custom-endpoints/{$id}");
    $response->assertStatus(204);
});

test('LLMProviderFactory routes custom: prefix to OpenAICompatibleProvider', function () {
    CustomEndpoint::create([
        'name' => 'Test Server',
        'slug' => 'test-server',
        'base_url' => 'http://localhost:8000/v1',
        'enabled' => true,
    ]);

    $factory = new LLMProviderFactory();
    $provider = $factory->make('custom:test-server:llama3');

    expect($provider)->toBeInstanceOf(OpenAICompatibleProvider::class);
});

test('LLMProviderFactory throws for unknown custom endpoint', function () {
    $factory = new LLMProviderFactory();
    $factory->make('custom:nonexistent:model');
})->throws(\RuntimeException::class, 'not found or disabled');

test('LLMProviderFactory identifies custom provider name', function () {
    $factory = new LLMProviderFactory();
    expect($factory->providerName('custom:my-server:llama3'))->toBe('custom');
});

// --- #254 Model health check and latency benchmarking ---

test('ModelHealthCheckService checks all providers', function () {
    $service = new ModelHealthCheckService();
    $results = $service->checkAll();

    expect($results)->toHaveKey('anthropic');
    expect($results)->toHaveKey('openai');
    expect($results)->toHaveKey('gemini');
    expect($results)->toHaveKey('grok');
    expect($results)->toHaveKey('ollama');

    foreach ($results as $provider => $result) {
        expect($result)->toHaveKey('status');
        expect($result)->toHaveKey('latency_ms');
    }
});

test('ModelHealthCheckService checks custom endpoint', function () {
    $endpoint = CustomEndpoint::create([
        'name' => 'Test',
        'base_url' => 'http://localhost:99999/v1',
        'enabled' => true,
    ]);

    $service = new ModelHealthCheckService();
    $result = $service->checkCustomEndpoint($endpoint);

    expect($result['status'])->toBe('unhealthy');
    expect($result)->toHaveKey('latency_ms');
    expect($result)->toHaveKey('error');

    // Should have updated the record
    $endpoint->refresh();
    expect($endpoint->health_status)->toBe('unhealthy');
    expect($endpoint->last_health_check)->not->toBeNull();
});

test('Model health API returns provider statuses', function () {
    $response = $this->getJson('/api/model-health');
    $response->assertOk();
    expect($response->json('data'))->toHaveKey('anthropic');
});

test('Model health API checks single provider', function () {
    $response = $this->getJson('/api/model-health/ollama');
    $response->assertOk();
    expect($response->json('data'))->toHaveKey('status');
});

// --- #255 Air-gap mode ---

test('AirGapService toggle works', function () {
    $service = new AirGapService(new NetworkGuard());

    expect($service->isEnabled())->toBeFalse();

    $service->enable();
    expect($service->isEnabled())->toBeTrue();

    $service->disable();
    expect($service->isEnabled())->toBeFalse();
});

test('AirGapService blocks cloud models when enabled', function () {
    $service = new AirGapService(new NetworkGuard());
    $service->enable();

    expect($service->isModelAllowed('grok-3'))->toBeFalse();
    expect($service->isModelAllowed('claude-sonnet-4-6'))->toBeFalse();
    expect($service->isModelAllowed('gpt-5.4'))->toBeFalse();
    expect($service->isModelAllowed('llama3'))->toBeTrue(); // Ollama default
});

test('AirGapService allows all models when disabled', function () {
    $service = new AirGapService(new NetworkGuard());

    expect($service->isModelAllowed('grok-3'))->toBeTrue();
    expect($service->isModelAllowed('claude-sonnet-4-6'))->toBeTrue();
});

test('AirGapService status returns full details', function () {
    $service = new AirGapService(new NetworkGuard());
    $service->enable();

    $status = $service->status();
    expect($status['enabled'])->toBeTrue();
    expect($status['blocked_providers'])->toContain('anthropic');
    expect($status['blocked_providers'])->toContain('openai');
    expect($status['blocked_providers'])->toContain('grok');
    expect($status['allowed_providers'])->toContain('ollama');
});

test('Air-gap API returns status', function () {
    $response = $this->getJson('/api/air-gap');
    $response->assertOk();
    expect($response->json('data.enabled'))->toBeFalse();
});

test('Air-gap API toggle requires admin role', function () {
    // Create viewer user
    $viewer = User::factory()->create();
    $this->org->users()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_organization_id' => $this->org->id]);

    $this->actingAs($viewer);

    $response = $this->postJson('/api/air-gap', ['enabled' => true]);
    $response->assertStatus(403);
});

test('Air-gap API toggle works for admin', function () {
    $response = $this->postJson('/api/air-gap', ['enabled' => true]);
    $response->assertOk();
    expect($response->json('data.enabled'))->toBeTrue();

    $response = $this->postJson('/api/air-gap', ['enabled' => false]);
    $response->assertOk();
    expect($response->json('data.enabled'))->toBeFalse();
});

test('AirGapService validates URLs', function () {
    $service = new AirGapService(new NetworkGuard());
    $service->enable();

    // Local URLs should be allowed
    expect($service->validateUrl('http://localhost:8080/api'))->toBeNull();
    expect($service->validateUrl('http://127.0.0.1:11434/api'))->toBeNull();

    // External URLs should be blocked
    $result = $service->validateUrl('https://api.openai.com/v1/chat');
    expect($result)->not->toBeNull();
    expect($result)->toContain('Air-gap');
});

// --- #256 Local model browser ---

test('LocalModelBrowserService discovers models', function () {
    $service = new LocalModelBrowserService();
    $models = $service->discover();

    // Should return array (may be empty if Ollama not running)
    expect($models)->toBeArray();
});

test('LocalModelBrowserService discovers custom local endpoints', function () {
    CustomEndpoint::create([
        'name' => 'Local LM Studio',
        'slug' => 'lm-studio',
        'base_url' => 'http://localhost:1234/v1',
        'models' => ['llama3', 'codellama'],
        'enabled' => true,
    ]);

    CustomEndpoint::create([
        'name' => 'Remote Server',
        'slug' => 'remote',
        'base_url' => 'https://api.example.com/v1',
        'models' => ['gpt-custom'],
        'enabled' => true,
    ]);

    $service = new LocalModelBrowserService();
    $customModels = $service->discoverCustomEndpoints();

    // Should only include local endpoint models
    expect($customModels)->toHaveCount(2);
    expect($customModels[0]['endpoint_slug'])->toBe('lm-studio');
    expect($customModels[1]['endpoint_slug'])->toBe('lm-studio');
});

test('Local models API returns results', function () {
    $response = $this->getJson('/api/local-models');
    $response->assertOk();
    expect($response->json('data'))->toBeArray();
});

test('LocalModelBrowserService formatBytes works correctly', function () {
    $service = new LocalModelBrowserService();
    $reflection = new \ReflectionMethod($service, 'formatBytes');

    expect($reflection->invoke($service, 1073741824))->toBe('1 GB');
    expect($reflection->invoke($service, 5368709120))->toBe('5 GB');
    expect($reflection->invoke($service, 524288))->toBe('512 KB');
    expect($reflection->invoke($service, 104857600))->toBe('100 MB');
});

// --- #257 Model performance comparison ---

test('ModelComparisonService summarizes empty results', function () {
    $factory = new LLMProviderFactory();
    $healthService = new ModelHealthCheckService();
    $service = new ModelComparisonService($factory, $healthService);

    // Calling with models that will fail (no API keys configured)
    $result = $service->compare(['grok-3']);

    expect($result)->toHaveKey('results');
    expect($result)->toHaveKey('summary');
    expect($result)->toHaveKey('prompt');
    expect($result)->toHaveKey('timestamp');
    expect($result['results'])->toHaveCount(1);
    expect($result['results'][0]['status'])->toBe('error');
});

test('ModelComparisonService summary picks best model', function () {
    $factory = new LLMProviderFactory();
    $healthService = new ModelHealthCheckService();
    $service = new ModelComparisonService($factory, $healthService);

    // Test summarize with mock data using reflection
    $reflection = new \ReflectionMethod($service, 'summarize');

    $mockResults = [
        [
            'model' => 'model-a',
            'status' => 'success',
            'latency_ms' => 200,
            'time_to_first_token_ms' => 50,
            'tokens_per_second' => 100,
            'output_length' => 500,
        ],
        [
            'model' => 'model-b',
            'status' => 'success',
            'latency_ms' => 100,
            'time_to_first_token_ms' => 30,
            'tokens_per_second' => 80,
            'output_length' => 800,
        ],
    ];

    $summary = $reflection->invoke($service, $mockResults);

    expect($summary['fastest'])->toBe('model-b');
    expect($summary['fastest_ttft'])->toBe('model-b');
    expect($summary['highest_throughput'])->toBe('model-a');
    expect($summary['most_verbose'])->toBe('model-b');
});

test('Model comparison API validates input', function () {
    $response = $this->postJson('/api/model-health/compare', []);
    $response->assertStatus(422);

    $response = $this->postJson('/api/model-health/compare', [
        'models' => [],
    ]);
    $response->assertStatus(422);
});

test('Model comparison API accepts valid request', function () {
    $response = $this->postJson('/api/model-health/compare', [
        'models' => ['grok-3'],
        'prompt' => 'Say hello',
    ]);

    $response->assertOk();
    expect($response->json('data.results'))->toHaveCount(1);
});

// --- Custom endpoint health check via API ---

test('Custom endpoint health check API works', function () {
    $endpoint = CustomEndpoint::create([
        'name' => 'Test EP',
        'base_url' => 'http://localhost:99999/v1',
        'enabled' => true,
    ]);

    $response = $this->postJson("/api/custom-endpoints/{$endpoint->id}/health");
    $response->assertOk();
    expect($response->json('data.status'))->toBe('unhealthy');
});

test('Custom endpoint model discovery API works', function () {
    $endpoint = CustomEndpoint::create([
        'name' => 'Test EP',
        'base_url' => 'http://localhost:99999/v1',
        'enabled' => true,
    ]);

    $response = $this->postJson("/api/custom-endpoints/{$endpoint->id}/discover");
    $response->assertOk();
    expect($response->json('data.models'))->toBeArray();
});

// --- Integration: factory includes custom endpoints in availableModels ---

test('LLMProviderFactory includes custom endpoints in availableModels', function () {
    CustomEndpoint::create([
        'name' => 'My LM Studio',
        'slug' => 'my-lm-studio',
        'base_url' => 'http://localhost:1234/v1',
        'models' => ['llama3'],
        'enabled' => true,
    ]);

    $factory = new LLMProviderFactory();
    $models = $factory->availableModels();

    $customSection = collect($models)->firstWhere('provider', 'custom:my-lm-studio');
    expect($customSection)->not->toBeNull();
    expect($customSection['label'])->toBe('My LM Studio');
    expect($customSection['configured'])->toBeTrue();
});

test('Disabled custom endpoints not listed in availableModels', function () {
    CustomEndpoint::create([
        'name' => 'Disabled EP',
        'slug' => 'disabled-ep',
        'base_url' => 'http://localhost:1234/v1',
        'models' => ['llama3'],
        'enabled' => false,
    ]);

    $factory = new LLMProviderFactory();
    $models = $factory->availableModels();

    $customSection = collect($models)->firstWhere('provider', 'custom:disabled-ep');
    expect($customSection)->toBeNull();
});
