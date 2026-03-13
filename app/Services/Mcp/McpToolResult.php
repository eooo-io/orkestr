<?php

namespace App\Services\Mcp;

class McpToolResult
{
    public function __construct(
        public readonly array $content,
        public readonly bool $isError = false,
    ) {}

    public static function fromResponse(McpResponse $response): self
    {
        if ($response->isError()) {
            return new self(
                content: [['type' => 'text', 'text' => $response->error->message]],
                isError: true,
            );
        }

        $result = $response->result ?? [];
        $content = $result['content'] ?? [['type' => 'text', 'text' => json_encode($result)]];
        $isError = $result['isError'] ?? false;

        return new self(content: $content, isError: $isError);
    }

    /**
     * Get the text content as a single string.
     */
    public function text(): string
    {
        return collect($this->content)
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");
    }

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'isError' => $this->isError,
        ];
    }
}
