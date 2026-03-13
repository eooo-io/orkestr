<?php

namespace App\Services\LLM;

use App\Models\AppSetting;

class OpenAIProvider implements LLMProviderInterface
{
    public function stream(string $systemPrompt, array $messages, string $model, int $maxTokens): \Generator
    {
        $apiKey = AppSetting::get('openai_api_key') ?: env('OPENAI_API_KEY');

        if (empty($apiKey)) {
            throw new \RuntimeException('OpenAI API key not configured. Set it in Settings.');
        }

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

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
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
            throw new \RuntimeException('OpenAI request failed: ' . $curlError);
        }

        if ($httpCode >= 400) {
            $decoded = json_decode($buffer, true);
            $errorMsg = $decoded['error']['message'] ?? "HTTP {$httpCode}";
            throw new \RuntimeException('OpenAI error: ' . $errorMsg);
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

            // Delta content
            $delta = $data['choices'][0]['delta'] ?? null;
            if ($delta && isset($delta['content']) && $delta['content'] !== '') {
                yield ['type' => 'text', 'text' => $delta['content']];
            }

            // Usage info (sent in the final chunk when stream_options.include_usage is true)
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
        // TODO: Implement full tool-use chat for OpenAI
        throw new \RuntimeException('OpenAI chat with tools not yet implemented. Use Anthropic models for agent execution.');
    }

    public function models(): array
    {
        return [
            'gpt-5.4',
            'gpt-5.4-thinking',
            'gpt-5-mini',
            'gpt-5.3-instant',
            'o3',
        ];
    }
}
