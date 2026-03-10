<?php

namespace App\Services\LLM;

use App\Models\AppSetting;
use Anthropic;

class AnthropicProvider implements LLMProviderInterface
{
    public function stream(string $systemPrompt, array $messages, string $model, int $maxTokens): \Generator
    {
        $apiKey = AppSetting::get('anthropic_api_key')
            ?: config('services.anthropic.api_key')
            ?: env('ANTHROPIC_API_KEY');

        if (empty($apiKey)) {
            throw new \RuntimeException('Anthropic API key not configured. Set it in Settings.');
        }

        $client = Anthropic::factory()
            ->withApiKey($apiKey)
            ->make();

        $params = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => $messages,
        ];

        if (! empty($systemPrompt)) {
            $params['system'] = $systemPrompt;
        }

        $stream = $client->messages()->createStreamed($params);

        foreach ($stream as $response) {
            $type = $response->type;

            if ($type === 'content_block_delta') {
                $text = $response->delta->text ?? '';
                if ($text !== '') {
                    yield ['type' => 'text', 'text' => $text];
                }
            } elseif ($type === 'message_start') {
                $inputTokens = $response->message->usage->inputTokens ?? null;
                if ($inputTokens !== null) {
                    yield ['type' => 'usage', 'input_tokens' => $inputTokens, 'output_tokens' => 0];
                }
            } elseif ($type === 'message_delta') {
                $outputTokens = $response->usage->outputTokens ?? null;
                $stopReason = $response->delta->stopReason ?? null;
                yield [
                    'type' => 'usage',
                    'output_tokens' => $outputTokens,
                    'stop_reason' => $stopReason,
                ];
            }
        }

        yield ['type' => 'done'];
    }

    public function models(): array
    {
        return [
            'claude-sonnet-4-20250514',
            'claude-opus-4-0-20250115',
            'claude-haiku-4-5-20251001',
        ];
    }
}
