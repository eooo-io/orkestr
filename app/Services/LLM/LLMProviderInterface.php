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
     * Send a non-streaming chat completion with optional tool definitions.
     *
     * Returns an associative array:
     *   [
     *     'content' => [['type' => 'text', 'text' => '...'], ['type' => 'tool_use', 'id' => '...', 'name' => '...', 'input' => [...]]],
     *     'stop_reason' => 'end_turn' | 'tool_use',
     *     'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
     *   ]
     */
    public function chat(string $systemPrompt, array $messages, string $model, int $maxTokens, array $tools = []): array;

    /**
     * Return available model identifiers for this provider.
     *
     * @return string[]
     */
    public function models(): array;
}
