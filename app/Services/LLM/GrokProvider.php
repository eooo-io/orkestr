<?php

namespace App\Services\LLM;

use App\Models\AppSetting;

/**
 * Grok/xAI provider — uses OpenAI-compatible API at api.x.ai.
 */
class GrokProvider implements LLMProviderInterface
{
    private const BASE_URL = 'https://api.x.ai/v1';

    protected function apiKey(): string
    {
        $key = AppSetting::get('grok_api_key') ?: env('GROK_API_KEY', '');

        if (empty($key)) {
            throw new \RuntimeException('Grok/xAI API key not configured. Set it in Settings.');
        }

        return $key;
    }

    public function stream(string $systemPrompt, array $messages, string $model, int $maxTokens): \Generator
    {
        $payload = [
            'model' => $model,
            'stream' => true,
            'stream_options' => ['include_usage' => true],
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

        $ch = curl_init(self::BASE_URL . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey(),
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 300,
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
            throw new \RuntimeException('Grok request failed: ' . $curlError);
        }

        if ($httpCode >= 400) {
            $decoded = json_decode($buffer, true);
            $errorMsg = $decoded['error']['message'] ?? "HTTP {$httpCode}";
            throw new \RuntimeException('Grok error: ' . $errorMsg);
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
        throw new \RuntimeException('Grok chat with tools not yet implemented. Use Anthropic models for agent execution.');
    }

    public function models(): array
    {
        return [
            'grok-3',
            'grok-3-fast',
            'grok-3-mini',
            'grok-3-mini-fast',
        ];
    }
}
