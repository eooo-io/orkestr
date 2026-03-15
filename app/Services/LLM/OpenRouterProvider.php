<?php

declare(strict_types=1);

namespace App\Services\LLM;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

/**
 * OpenRouter provider — single API key access to 200+ models.
 *
 * Uses the OpenAI-compatible API at https://openrouter.ai/api/v1
 * with additional HTTP-Referer and X-Title headers per OpenRouter docs.
 *
 * Model format: "openrouter:{model_id}" e.g. "openrouter:anthropic/claude-3.5-sonnet"
 */
class OpenRouterProvider implements LLMProviderInterface
{
    private const BASE_URL = 'https://openrouter.ai/api/v1';

    private const MODELS_CACHE_KEY = 'openrouter:models';

    private const MODELS_CACHE_TTL = 3600; // 1 hour

    public function stream(string $systemPrompt, array $messages, string $model, int $maxTokens): \Generator
    {
        $apiKey = $this->getApiKey();
        $model = $this->cleanModelName($model);

        $payload = [
            'model' => $model,
            'stream' => true,
            'max_tokens' => $maxTokens,
            'messages' => [],
        ];

        if (! empty($systemPrompt)) {
            $payload['messages'][] = ['role' => 'system', 'content' => $systemPrompt];
        }

        foreach ($messages as $msg) {
            $payload['messages'][] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        $ch = curl_init(self::BASE_URL . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $this->buildHeaders($apiKey),
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $buffer = '';
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$buffer) {
            $buffer .= $data;

            return strlen($data);
        });

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new \RuntimeException('OpenRouter request failed: ' . $curlError);
        }

        if ($httpCode >= 400) {
            $decoded = json_decode($buffer, true);
            $errorMsg = $decoded['error']['message'] ?? $decoded['error'] ?? "HTTP {$httpCode}";
            throw new \RuntimeException('OpenRouter error: ' . $errorMsg);
        }

        $lines = explode("\n", $buffer);
        foreach ($lines as $line) {
            $line = trim($line);
            if (! str_starts_with($line, 'data: ')) {
                continue;
            }

            $json = substr($line, 6);
            if ($json === '[DONE]') {
                break;
            }

            $data = json_decode($json, true);
            if (! $data) {
                continue;
            }

            $delta = $data['choices'][0]['delta'] ?? null;
            if ($delta && isset($delta['content']) && $delta['content'] !== '') {
                yield ['type' => 'text', 'text' => $delta['content']];
            }

            if (isset($data['usage'])) {
                yield [
                    'type' => 'usage',
                    'input_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                    'output_tokens' => $data['usage']['completion_tokens'] ?? 0,
                ];
            }
        }

        yield ['type' => 'done'];
    }

    public function chat(string $systemPrompt, array $messages, string $model, int $maxTokens, array $tools = []): array
    {
        $apiKey = $this->getApiKey();
        $model = $this->cleanModelName($model);

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => [],
        ];

        if (! empty($systemPrompt)) {
            $payload['messages'][] = ['role' => 'system', 'content' => $systemPrompt];
        }

        foreach ($messages as $msg) {
            $payload['messages'][] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        if (! empty($tools)) {
            $payload['tools'] = $tools;
        }

        $ch = curl_init(self::BASE_URL . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $this->buildHeaders($apiKey),
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 300,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new \RuntimeException('OpenRouter request failed: ' . $curlError);
        }

        if ($httpCode >= 400) {
            $decoded = json_decode($response, true);
            $errorMsg = $decoded['error']['message'] ?? "HTTP {$httpCode}";
            throw new \RuntimeException('OpenRouter error: ' . $errorMsg);
        }

        $data = json_decode($response, true);
        $choice = $data['choices'][0] ?? [];
        $message = $choice['message'] ?? [];

        $content = [];
        if (isset($message['content'])) {
            $content[] = ['type' => 'text', 'text' => $message['content']];
        }

        foreach ($message['tool_calls'] ?? [] as $toolCall) {
            $content[] = [
                'type' => 'tool_use',
                'id' => $toolCall['id'],
                'name' => $toolCall['function']['name'],
                'input' => json_decode($toolCall['function']['arguments'] ?? '{}', true) ?? [],
            ];
        }

        $stopReason = match ($choice['finish_reason'] ?? 'stop') {
            'tool_calls' => 'tool_use',
            'length' => 'max_tokens',
            default => 'end_turn',
        };

        return [
            'content' => $content,
            'stop_reason' => $stopReason,
            'usage' => [
                'input_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                'output_tokens' => $data['usage']['completion_tokens'] ?? 0,
            ],
        ];
    }

    public function models(): array
    {
        return Cache::remember(self::MODELS_CACHE_KEY, self::MODELS_CACHE_TTL, function () {
            return $this->fetchModelsFromApi();
        });
    }

    /**
     * Fetch available models with pricing and context length.
     *
     * @return array<int, array{id: string, name: string, context_length: int, pricing: array{prompt: string, completion: string}}>
     */
    public function modelsWithDetails(): array
    {
        return Cache::remember(self::MODELS_CACHE_KEY . ':details', self::MODELS_CACHE_TTL, function () {
            return $this->fetchModelDetailsFromApi();
        });
    }

    private function getApiKey(): string
    {
        $apiKey = AppSetting::get('openrouter_api_key') ?: env('OPENROUTER_API_KEY');

        if (empty($apiKey)) {
            throw new \RuntimeException('OpenRouter API key not configured. Set it in Settings.');
        }

        return $apiKey;
    }

    /**
     * @return string[]
     */
    private function buildHeaders(string $apiKey): array
    {
        return [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'HTTP-Referer: ' . config('app.url', 'https://eooo.ai'),
            'X-Title: Orkestr by eooo.ai',
        ];
    }

    private function cleanModelName(string $model): string
    {
        if (str_starts_with($model, 'openrouter:')) {
            return substr($model, strlen('openrouter:'));
        }

        return $model;
    }

    /**
     * @return string[]
     */
    private function fetchModelsFromApi(): array
    {
        try {
            $ch = curl_init(self::BASE_URL . '/models');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $models = [];
                foreach ($data['data'] ?? [] as $model) {
                    if (isset($model['id'])) {
                        $models[] = 'openrouter:' . $model['id'];
                    }
                }

                return $models;
            }
        } catch (\Throwable) {
            // API not reachable
        }

        return [];
    }

    /**
     * @return array<int, array{id: string, name: string, context_length: int, pricing: array{prompt: string, completion: string}}>
     */
    private function fetchModelDetailsFromApi(): array
    {
        try {
            $ch = curl_init(self::BASE_URL . '/models');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $models = [];
                foreach ($data['data'] ?? [] as $model) {
                    if (! isset($model['id'])) {
                        continue;
                    }

                    $models[] = [
                        'id' => 'openrouter:' . $model['id'],
                        'name' => $model['name'] ?? $model['id'],
                        'context_length' => $model['context_length'] ?? 0,
                        'pricing' => [
                            'prompt' => $model['pricing']['prompt'] ?? '0',
                            'completion' => $model['pricing']['completion'] ?? '0',
                        ],
                    ];
                }

                return $models;
            }
        } catch (\Throwable) {
            // API not reachable
        }

        return [];
    }
}
