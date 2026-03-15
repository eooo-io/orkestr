<?php

namespace App\Services\LLM;

use App\Models\AppSetting;
use App\Models\CustomEndpoint;

class ModelHealthCheckService
{
    /**
     * Check health of a specific provider endpoint.
     *
     * @return array{status: string, latency_ms: float|null, error: string|null}
     */
    public function checkProvider(string $provider): array
    {
        $start = microtime(true);

        try {
            $url = $this->getHealthUrl($provider);
            if (! $url) {
                return ['status' => 'unconfigured', 'latency_ms' => null, 'error' => 'No endpoint URL'];
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HTTPHEADER => $this->getHeaders($provider),
            ]);

            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $latency = round((microtime(true) - $start) * 1000, 2);

            if ($curlError) {
                return ['status' => 'unhealthy', 'latency_ms' => $latency, 'error' => $curlError];
            }

            if ($httpCode >= 400) {
                return ['status' => 'unhealthy', 'latency_ms' => $latency, 'error' => "HTTP {$httpCode}"];
            }

            return ['status' => 'healthy', 'latency_ms' => $latency, 'error' => null];
        } catch (\Throwable $e) {
            $latency = round((microtime(true) - $start) * 1000, 2);

            return ['status' => 'unhealthy', 'latency_ms' => $latency, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check health of a custom endpoint and update its record.
     */
    public function checkCustomEndpoint(CustomEndpoint $endpoint): array
    {
        $start = microtime(true);

        try {
            $headers = ['Content-Type: application/json'];
            if ($endpoint->api_key) {
                $headers[] = 'Authorization: Bearer ' . $endpoint->api_key;
            }

            $ch = curl_init(rtrim($endpoint->base_url, '/') . '/models');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HTTPHEADER => $headers,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $latency = round((microtime(true) - $start) * 1000, 2);

            $status = ($curlError || $httpCode >= 400) ? 'unhealthy' : 'healthy';

            // Update available models from /models endpoint
            $models = [];
            if ($status === 'healthy' && $response) {
                $data = json_decode($response, true);
                foreach ($data['data'] ?? [] as $model) {
                    if (! empty($model['id'])) {
                        $models[] = $model['id'];
                    }
                }
            }

            $endpoint->update([
                'health_status' => $status,
                'last_health_check' => now(),
                'avg_latency_ms' => $latency,
                'models' => ! empty($models) ? $models : $endpoint->models,
            ]);

            return [
                'status' => $status,
                'latency_ms' => $latency,
                'models' => $models,
                'error' => $curlError ?: ($httpCode >= 400 ? "HTTP {$httpCode}" : null),
            ];
        } catch (\Throwable $e) {
            $latency = round((microtime(true) - $start) * 1000, 2);

            $endpoint->update([
                'health_status' => 'unhealthy',
                'last_health_check' => now(),
            ]);

            return ['status' => 'unhealthy', 'latency_ms' => $latency, 'models' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Check all providers and custom endpoints.
     */
    public function checkAll(): array
    {
        $results = [];

        foreach (['anthropic', 'openai', 'gemini', 'grok', 'ollama'] as $provider) {
            $results[$provider] = $this->checkProvider($provider);
        }

        $customEndpoints = CustomEndpoint::where('enabled', true)->get();
        foreach ($customEndpoints as $endpoint) {
            $results['custom:' . $endpoint->slug] = $this->checkCustomEndpoint($endpoint);
        }

        return $results;
    }

    /**
     * Benchmark latency for a specific model with a simple prompt.
     */
    public function benchmark(string $model, LLMProviderInterface $provider): array
    {
        $start = microtime(true);
        $tokensGenerated = 0;

        try {
            $generator = $provider->stream(
                'Respond with exactly: "OK"',
                [['role' => 'user', 'content' => 'ping']],
                $model,
                10,
            );

            foreach ($generator as $event) {
                if ($event['type'] === 'usage') {
                    $tokensGenerated = $event['output_tokens'] ?? 0;
                }
            }

            $totalMs = round((microtime(true) - $start) * 1000, 2);

            return [
                'model' => $model,
                'latency_ms' => $totalMs,
                'tokens_generated' => $tokensGenerated,
                'tokens_per_second' => $tokensGenerated > 0 && $totalMs > 0
                    ? round($tokensGenerated / ($totalMs / 1000), 2)
                    : null,
                'status' => 'success',
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'model' => $model,
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
                'tokens_generated' => 0,
                'tokens_per_second' => null,
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function getHealthUrl(string $provider): ?string
    {
        return match ($provider) {
            'anthropic' => 'https://api.anthropic.com/v1/messages',
            'openai' => 'https://api.openai.com/v1/models',
            'gemini' => 'https://generativelanguage.googleapis.com/v1/models',
            'grok' => 'https://api.x.ai/v1/models',
            'ollama' => rtrim(AppSetting::get('ollama_url') ?: env('OLLAMA_URL', 'http://localhost:11434'), '/') . '/api/tags',
            default => null,
        };
    }

    protected function getHeaders(string $provider): array
    {
        $headers = ['Content-Type: application/json'];

        $key = match ($provider) {
            'anthropic' => AppSetting::get('anthropic_api_key') ?: config('services.anthropic.api_key') ?: env('ANTHROPIC_API_KEY'),
            'openai' => AppSetting::get('openai_api_key') ?: env('OPENAI_API_KEY'),
            'gemini' => AppSetting::get('gemini_api_key') ?: env('GEMINI_API_KEY'),
            'grok' => AppSetting::get('grok_api_key') ?: env('GROK_API_KEY'),
            default => null,
        };

        if ($key && $provider === 'anthropic') {
            $headers[] = 'x-api-key: ' . $key;
            $headers[] = 'anthropic-version: 2023-06-01';
        } elseif ($key) {
            $headers[] = 'Authorization: Bearer ' . $key;
        }

        return $headers;
    }
}
