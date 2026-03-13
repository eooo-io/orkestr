<?php

namespace App\Services\Mcp;

class McpResponse
{
    public function __construct(
        public readonly string|int|null $id,
        public readonly mixed $result,
        public readonly ?McpError $error,
    ) {}

    public function isError(): bool
    {
        return $this->error !== null;
    }

    public function isSuccess(): bool
    {
        return $this->error === null;
    }
}
