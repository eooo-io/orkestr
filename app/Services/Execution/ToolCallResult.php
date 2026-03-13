<?php

namespace App\Services\Execution;

class ToolCallResult
{
    public function __construct(
        public readonly string $toolName,
        public readonly array $content,
        public readonly bool $isError,
        public readonly int $durationMs,
    ) {}

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
            'tool_name' => $this->toolName,
            'content' => $this->content,
            'is_error' => $this->isError,
            'duration_ms' => $this->durationMs,
        ];
    }
}
