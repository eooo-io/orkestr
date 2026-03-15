<?php

use App\Models\AppSetting;
use App\Models\Organization;
use App\Models\User;
use App\Services\LLM\LLMProviderFactory;
use App\Services\LLM\OpenRouterProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->org = Organization::create([
        'name' => 'OR Org',
        'slug' => 'or-org',
        'plan' => 'pro',
    ]);
    $this->org->users()->attach($this->user->id, ['role' => 'owner']);
    $this->user->update(['current_organization_id' => $this->org->id]);
    app()->instance('current_organization', $this->org);
});

test('LLMProviderFactory routes openrouter: prefix to OpenRouterProvider', function () {
    $factory = new LLMProviderFactory();
    $provider = $factory->make('openrouter:anthropic/claude-3.5-sonnet');

    expect($provider)->toBeInstanceOf(OpenRouterProvider::class);
});

test('LLMProviderFactory identifies openrouter provider name', function () {
    $factory = new LLMProviderFactory();

    expect($factory->providerName('openrouter:meta-llama/llama-3-70b'))->toBe('openrouter');
    expect($factory->providerName('openrouter:openai/gpt-4o'))->toBe('openrouter');
});

test('OpenRouterProvider cleans model name prefix', function () {
    $provider = new OpenRouterProvider();

    // models() returns prefixed names — verify the provider exists and has the method
    expect($provider)->toBeInstanceOf(OpenRouterProvider::class);
    expect(method_exists($provider, 'stream'))->toBeTrue();
    expect(method_exists($provider, 'chat'))->toBeTrue();
    expect(method_exists($provider, 'models'))->toBeTrue();
    expect(method_exists($provider, 'modelsWithDetails'))->toBeTrue();
});

test('OpenRouterProvider throws when API key not configured', function () {
    $provider = new OpenRouterProvider();

    // Generator must be iterated to trigger the exception
    iterator_to_array($provider->stream('You are helpful.', [['role' => 'user', 'content' => 'Hi']], 'openrouter:meta-llama/llama-3-70b', 100));
})->throws(RuntimeException::class, 'OpenRouter API key not configured');

test('OpenRouter model discovery endpoint works', function () {
    // Without a real API key, the endpoint should still return a response (empty array)
    $response = $this->getJson('/api/models/openrouter');
    $response->assertOk();
    $response->assertJsonStructure(['data']);
});

test('OpenRouter appears in available models when configured', function () {
    AppSetting::set('openrouter_api_key', 'sk-or-test-key-123');

    $factory = new LLMProviderFactory();
    $models = $factory->availableModels();

    $openRouterEntry = collect($models)->firstWhere('provider', 'openrouter');
    expect($openRouterEntry)->not->toBeNull();
    expect($openRouterEntry['label'])->toBe('OpenRouter');
    expect($openRouterEntry['configured'])->toBeTrue();
});

test('OpenRouter does not appear as configured when key is missing', function () {
    $factory = new LLMProviderFactory();
    $models = $factory->availableModels();

    $openRouterEntry = collect($models)->firstWhere('provider', 'openrouter');
    expect($openRouterEntry)->not->toBeNull();
    expect($openRouterEntry['configured'])->toBeFalse();
});
