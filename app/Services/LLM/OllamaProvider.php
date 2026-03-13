<?php

namespace App\Services\LLM;

use App\Models\AppSetting;

class OllamaProvider implements LLMProviderInterface
{
    protected function baseUrl(): string
    {
        return rtrim(
            AppSetting::get('ollama_url') ?: env('OLLAMA_URL', 'http://localhost:11434'),
            '/',
        );
    }

    public function stream(string $systemPrompt, array $messages, string $model, int $maxTokens): \Generator
    {
        $baseUrl = $this->baseUrl();

        $payload = [
            'model' => $model,
            'stream' => true,
            'max_completion_tokens' => $maxTokens,
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

        $ch = curl_init("{$baseUrl}/v1/chat/completions");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_CONNECTTIMEOUT => 5,
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
            throw new \RuntimeException('Ollama request failed: ' . $curlError . '. Is Ollama running?');
        }

        if ($httpCode >= 400) {
            $decoded = json_decode($buffer, true);
            $errorMsg = $decoded['error']['message'] ?? $decoded['error'] ?? "HTTP {$httpCode}";
            throw new \RuntimeException('Ollama error: ' . $errorMsg);
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
        throw new \RuntimeException('Ollama chat with tools not yet implemented. Use Anthropic models for agent execution.');
    }

    public function models(): array
    {
        try {
            $baseUrl = $this->baseUrl();
            $ch = curl_init("{$baseUrl}/api/tags");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $models = [];
                foreach ($data['models'] ?? [] as $model) {
                    $models[] = $model['name'] ?? $model['model'] ?? '';
                }
                $models = array_filter($models);
                if (! empty($models)) {
                    return array_values($models);
                }
            }
        } catch (\Throwable) {
            // Ollama not running, return fallback
        }

        return ['llama3.3', 'codellama', 'mistral'];
    }
}
