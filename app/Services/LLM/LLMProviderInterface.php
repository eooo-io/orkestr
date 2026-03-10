<?php

namespace App\Services\LLM;

interface LLMProviderInterface
{
    /**
     * Stream a chat completion.
     *
     * Yields associative arrays:
     *   ['type' => 'text', 'text' => 'chunk...']
     *   ['type' => 'usage', 'input_tokens' => 100, 'output_tokens' => 50]
     *   ['type' => 'done']
     *
     * @return \Generator<int, array{type: string, text?: string, input_tokens?: int, output_tokens?: int}>
     */
    public function stream(string $systemPrompt, array $messages, string $model, int $maxTokens): \Generator;

    /**
     * Return available model identifiers for this provider.
     *
     * @return string[]
     */
    public function models(): array;
}
