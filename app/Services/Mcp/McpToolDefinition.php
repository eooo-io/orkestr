<?php

namespace App\Services\Mcp;

class McpToolDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $inputSchema = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            description: $data['description'] ?? '',
            inputSchema: $data['inputSchema'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'inputSchema' => $this->inputSchema,
        ];
    }
}
