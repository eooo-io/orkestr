<?php

namespace App\Services\LLM;

/**
 * Generic OpenAI-compatible provider for vLLM, TGI, LM Studio, etc.
 */
class OpenAICompatibleProvider implements LLMProviderInterface
{
    public function __construct(
        protected string $baseUrl,
        protected ?string $apiKey = null,
        protected string $providerName = 'Custom',
    ) {
        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    public function stream(string $systemPrompt, array $messages, string $model, int $maxTokens): \Generator
    {
        // Strip "custom:slug:" prefix if present
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

        $headers = ['Content-Type: application/json'];
        if ($this->apiKey) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $ch = curl_init($this->baseUrl . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 600,
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
            throw new \RuntimeException("{$this->providerName} request failed: {$curlError}");
        }

        if ($httpCode >= 400) {
            $decoded = json_decode($buffer, true);
            $errorMsg = $decoded['error']['message'] ?? $decoded['error'] ?? "HTTP {$httpCode}";
            throw new \RuntimeException("{$this->providerName} error: {$errorMsg}");
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
        throw new \RuntimeException("{$this->providerName} chat with tools not yet implemented.");
    }

    public function models(): array
    {
        try {
            $headers = ['Content-Type: application/json'];
            if ($this->apiKey) {
                $headers[] = 'Authorization: Bearer ' . $this->apiKey;
            }

            $ch = curl_init($this->baseUrl . '/models');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $models = [];
                foreach ($data['data'] ?? [] as $model) {
                    $models[] = $model['id'] ?? '';
                }

                return array_values(array_filter($models));
            }
        } catch (\Throwable) {
            // Endpoint not reachable
        }

        return [];
    }

    protected function cleanModelName(string $model): string
    {
        // Strip "custom:slug:actual-model" → "actual-model"
        if (str_starts_with($model, 'custom:')) {
            $parts = explode(':', $model, 3);

            return $parts[2] ?? $parts[1] ?? $model;
        }

        return $model;
    }
}
