<?php

namespace App\Services\LLM;

use App\Models\AppSetting;

class LLMProviderFactory
{
    /**
     * Create a provider instance for the given model name.
     */
    public function make(string $model): LLMProviderInterface
    {
        return match (true) {
            str_starts_with($model, 'claude-') => new AnthropicProvider(),
            str_starts_with($model, 'gpt-'),
            str_starts_with($model, 'o3'),
            str_starts_with($model, 'o4') => new OpenAIProvider(),
            str_starts_with($model, 'gemini-') => new GeminiProvider(),
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
            str_starts_with($model, 'o3'),
            str_starts_with($model, 'o4') => 'openai',
            str_starts_with($model, 'gemini-') => 'gemini',
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

        // Ollama is considered "configured" if we can reach it
        $ollamaProvider = new OllamaProvider();
        $ollamaModels = $ollamaProvider->models();
        $ollamaConfigured = $this->isOllamaReachable();

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
                'provider' => 'ollama',
                'label' => 'Ollama (Local)',
                'configured' => $ollamaConfigured,
                'models' => $this->formatModels($ollamaModels, 'ollama'),
            ],
        ];
    }

    protected function formatModels(array $modelIds, string $provider): array
    {
        $contextWindows = [
            'claude-sonnet-4-20250514' => 200000,
            'claude-opus-4-0-20250115' => 200000,
            'claude-haiku-4-5-20251001' => 200000,
            'gpt-4o' => 128000,
            'gpt-4.1' => 1047576,
            'gpt-4.1-mini' => 1047576,
            'o3' => 200000,
            'o4-mini' => 200000,
            'gemini-2.5-pro' => 1048576,
            'gemini-2.5-flash' => 1048576,
        ];

        $labels = [
            'claude-sonnet-4-20250514' => 'Claude Sonnet 4',
            'claude-opus-4-0-20250115' => 'Claude Opus 4',
            'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5',
            'gpt-4o' => 'GPT-4o',
            'gpt-4.1' => 'GPT-4.1',
            'gpt-4.1-mini' => 'GPT-4.1 Mini',
            'o3' => 'o3',
            'o4-mini' => 'o4-mini',
            'gemini-2.5-pro' => 'Gemini 2.5 Pro',
            'gemini-2.5-flash' => 'Gemini 2.5 Flash',
        ];

        return array_map(fn (string $id) => [
            'id' => $id,
            'name' => $labels[$id] ?? $id,
            'provider' => $provider,
            'context_window' => $contextWindows[$id] ?? 0,
        ], $modelIds);
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
