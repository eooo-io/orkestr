<?php

namespace App\Services\LLM;

use App\Models\AppSetting;

class GeminiProvider implements LLMProviderInterface
{
    public function stream(string $systemPrompt, array $messages, string $model, int $maxTokens): \Generator
    {
        $apiKey = AppSetting::get('gemini_api_key') ?: env('GEMINI_API_KEY');

        if (empty($apiKey)) {
            throw new \RuntimeException('Gemini API key not configured. Set it in Settings.');
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:streamGenerateContent?alt=sse&key={$apiKey}";

        $payload = [
            'contents' => [],
            'generationConfig' => [
                'maxOutputTokens' => $maxTokens,
            ],
        ];

        if (! empty($systemPrompt)) {
            $payload['system_instruction'] = [
                'parts' => [['text' => $systemPrompt]],
            ];
        }

        // Map messages to Gemini's format
        foreach ($messages as $msg) {
            $role = $msg['role'] === 'assistant' ? 'model' : 'user';
            $payload['contents'][] = [
                'role' => $role,
                'parts' => [['text' => $msg['content']]],
            ];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
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
            throw new \RuntimeException('Gemini request failed: ' . $curlError);
        }

        if ($httpCode >= 400) {
            $decoded = json_decode($buffer, true);
            $errorMsg = $decoded['error']['message'] ?? "HTTP {$httpCode}";
            throw new \RuntimeException('Gemini error: ' . $errorMsg);
        }

        $totalInputTokens = 0;
        $totalOutputTokens = 0;

        $lines = explode("\n", $buffer);
        foreach ($lines as $line) {
            $line = trim($line);
            if (! str_starts_with($line, 'data: ')) {
                continue;
            }

            $json = substr($line, 6);
            $data = json_decode($json, true);
            if (! $data) {
                continue;
            }

            // Extract text from candidates
            $candidates = $data['candidates'] ?? [];
            foreach ($candidates as $candidate) {
                $parts = $candidate['content']['parts'] ?? [];
                foreach ($parts as $part) {
                    if (isset($part['text']) && $part['text'] !== '') {
                        yield ['type' => 'text', 'text' => $part['text']];
                    }
                }
            }

            // Usage metadata
            if (isset($data['usageMetadata'])) {
                $meta = $data['usageMetadata'];
                $totalInputTokens = $meta['promptTokenCount'] ?? $totalInputTokens;
                $totalOutputTokens = $meta['candidatesTokenCount'] ?? $totalOutputTokens;
            }
        }

        if ($totalInputTokens > 0 || $totalOutputTokens > 0) {
            yield [
                'type' => 'usage',
                'input_tokens' => $totalInputTokens,
                'output_tokens' => $totalOutputTokens,
            ];
        }

        yield ['type' => 'done'];
    }

    public function chat(string $systemPrompt, array $messages, string $model, int $maxTokens, array $tools = []): array
    {
        throw new \RuntimeException('Gemini chat with tools not yet implemented. Use Anthropic models for agent execution.');
    }

    public function models(): array
    {
        return [
            'gemini-3.1-pro-preview',
            'gemini-3-flash-preview',
        ];
    }
}
