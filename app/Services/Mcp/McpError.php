<?php

namespace App\Services\Mcp;

class McpError
{
    public function __construct(
        public readonly int $code,
        public readonly string $message,
        public readonly mixed $data = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'code' => $this->code,
            'message' => $this->message,
            'data' => $this->data,
        ], fn ($v) => $v !== null);
    }
}
