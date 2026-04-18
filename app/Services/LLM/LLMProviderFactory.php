<?php

namespace App\Services\LLM;

use App\Models\AppSetting;
use App\Models\CustomEndpoint;

class LLMProviderFactory
{
    /**
     * Static context window lookup. Shared between formatModels() and contextWindowFor().
     *
     * @var array<string, int>
     */
    public const CONTEXT_WINDOWS = [
        'claude-opus-4-6' => 200000,
        'claude-sonnet-4-6' => 200000,
        'claude-haiku-4-5-20251001' => 200000,
        'gpt-5.4' => 1048576,
        'gpt-5.4-thinking' => 1048576,
        'gpt-5-mini' => 1048576,
        'gpt-5.3-instant' => 1048576,
        'o3' => 200000,
        'gemini-3.1-pro' => 1048576,
        'gemini-3-flash' => 1048576,
        'gemini-3.1-flash-lite' => 1048576,
        'grok-3' => 131072,
        'grok-3-fast' => 131072,
        'grok-3-mini' => 131072,
        'grok-3-mini-fast' => 131072,
    ];

    /**
     * Return the context window for a model, or 0 if unknown.
     */
    public function contextWindowFor(string $model): int
    {
        return self::CONTEXT_WINDOWS[$model] ?? 0;
    }

    /**
     * Create a provider instance for the given model name.
     */
    public function make(string $model): LLMProviderInterface
    {
        return match (true) {
            str_starts_with($model, 'claude-') => new AnthropicProvider(),
            str_starts_with($model, 'gpt-'),
            str_starts_with($model, 'o3') => new OpenAIProvider(),
            str_starts_with($model, 'gemini-') => new GeminiProvider(),
            str_starts_with($model, 'grok-') => new GrokProvider(),
            str_starts_with($model, 'openrouter:') => new OpenRouterProvider(),
            str_starts_with($model, 'custom:') => $this->makeCustomProvider($model),
            default => new OllamaProvider(),
        };
    }

    /**
     * Determine provider name from a model string.
     */
    public function providerName(string $model): string
    {
        return match (true) {
            str_starts_with($model, 'claude-') => 'anthropic',
            str_starts_with($model, 'gpt-'),
            str_starts_with($model, 'o3') => 'openai',
            str_starts_with($model, 'gemini-') => 'gemini',
            str_starts_with($model, 'grok-') => 'grok',
            str_starts_with($model, 'openrouter:') => 'openrouter',
            str_starts_with($model, 'custom:') => 'custom',
            default => 'ollama',
        };
    }

    /**
     * Return all models grouped by provider with configured status.
     */
    public function availableModels(): array
    {
        $anthropicConfigured = ! empty(
            AppSetting::get('anthropic_api_key')
            ?: config('services.anthropic.api_key')
            ?: env('ANTHROPIC_API_KEY')
        );

        $openaiConfigured = ! empty(
            AppSetting::get('openai_api_key') ?: env('OPENAI_API_KEY')
        );

        $geminiConfigured = ! empty(
            AppSetting::get('gemini_api_key') ?: env('GEMINI_API_KEY')
        );

        $grokConfigured = ! empty(
            AppSetting::get('grok_api_key') ?: env('GROK_API_KEY')
        );

        $openRouterConfigured = ! empty(
            AppSetting::get('openrouter_api_key') ?: env('OPENROUTER_API_KEY')
        );

        // Ollama is considered "configured" if we can reach it
        $ollamaProvider = new OllamaProvider();
        $ollamaModels = $ollamaProvider->models();
        $ollamaConfigured = $this->isOllamaReachable();

        // Custom OpenAI-compatible endpoints
        $customEndpoints = CustomEndpoint::where('enabled', true)->get();

        return [
            [
                'provider' => 'anthropic',
                'label' => 'Anthropic',
                'configured' => $anthropicConfigured,
                'models' => $this->formatModels((new AnthropicProvider())->models(), 'anthropic'),
            ],
            [
                'provider' => 'openai',
                'label' => 'OpenAI',
                'configured' => $openaiConfigured,
                'models' => $this->formatModels((new OpenAIProvider())->models(), 'openai'),
            ],
            [
                'provider' => 'gemini',
                'label' => 'Google Gemini',
                'configured' => $geminiConfigured,
                'models' => $this->formatModels((new GeminiProvider())->models(), 'gemini'),
            ],
            [
                'provider' => 'grok',
                'label' => 'Grok (xAI)',
                'configured' => $grokConfigured,
                'models' => $this->formatModels((new GrokProvider())->models(), 'grok'),
            ],
            [
                'provider' => 'openrouter',
                'label' => 'OpenRouter',
                'configured' => $openRouterConfigured,
                'models' => $openRouterConfigured
                    ? $this->formatOpenRouterModels((new OpenRouterProvider())->modelsWithDetails())
                    : [],
            ],
            [
                'provider' => 'ollama',
                'label' => 'Ollama (Local)',
                'configured' => $ollamaConfigured,
                'models' => $this->formatModels($ollamaModels, 'ollama'),
            ],
            ...array_map(fn (CustomEndpoint $ep) => [
                'provider' => 'custom:' . $ep->slug,
                'label' => $ep->name,
                'configured' => true,
                'models' => $this->formatModels(
                    array_map(fn (string $m) => "custom:{$ep->slug}:{$m}", $ep->models ?? []),
                    'custom:' . $ep->slug,
                ),
            ], $customEndpoints->all()),
        ];
    }

    protected function formatModels(array $modelIds, string $provider): array
    {
        $labels = [
            'claude-opus-4-6' => 'Claude Opus 4.6',
            'claude-sonnet-4-6' => 'Claude Sonnet 4.6',
            'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5',
            'gpt-5.4' => 'GPT-5.4',
            'gpt-5.4-thinking' => 'GPT-5.4 Thinking',
            'gpt-5-mini' => 'GPT-5 Mini',
            'gpt-5.3-instant' => 'GPT-5.3 Instant',
            'o3' => 'o3',
            'gemini-3.1-pro' => 'Gemini 3.1 Pro',
            'gemini-3-flash' => 'Gemini 3 Flash',
            'gemini-3.1-flash-lite' => 'Gemini 3.1 Flash Lite',
            'grok-3' => 'Grok 3',
            'grok-3-fast' => 'Grok 3 Fast',
            'grok-3-mini' => 'Grok 3 Mini',
            'grok-3-mini-fast' => 'Grok 3 Mini Fast',
        ];

        return array_map(fn (string $id) => [
            'id' => $id,
            'name' => $labels[$id] ?? $id,
            'provider' => $provider,
            'context_window' => self::CONTEXT_WINDOWS[$id] ?? 0,
        ], $modelIds);
    }

    /**
     * Create a custom OpenAI-compatible provider from endpoint config.
     * Model format: "custom:{slug}:{model_name}"
     */
    protected function makeCustomProvider(string $model): OpenAICompatibleProvider
    {
        // Parse "custom:slug:model" or "custom:slug"
        $parts = explode(':', $model, 3);
        $slug = $parts[1] ?? '';

        $endpoint = CustomEndpoint::where('slug', $slug)->where('enabled', true)->first();

        if (! $endpoint) {
            throw new \RuntimeException("Custom endpoint '{$slug}' not found or disabled.");
        }

        return new OpenAICompatibleProvider(
            baseUrl: $endpoint->base_url,
            apiKey: $endpoint->api_key,
            providerName: $endpoint->name,
        );
    }

    protected function formatOpenRouterModels(array $models): array
    {
        return array_map(fn (array $m) => [
            'id' => $m['id'],
            'name' => $m['name'],
            'provider' => 'openrouter',
            'context_window' => $m['context_length'] ?? 0,
            'pricing' => $m['pricing'] ?? null,
        ], $models);
    }

    protected function isOllamaReachable(): bool
    {
        try {
            $baseUrl = rtrim(
                AppSetting::get('ollama_url') ?: env('OLLAMA_URL', 'http://localhost:11434'),
                '/',
            );

            $ch = curl_init("{$baseUrl}/api/tags");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 2,
                CURLOPT_CONNECTTIMEOUT => 1,
            ]);

            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode === 200;
        } catch (\Throwable) {
            return false;
        }
    }
}
