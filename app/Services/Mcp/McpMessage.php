<?php

namespace App\Services\Mcp;

class McpMessage
{
    public function __construct(
        public readonly string $method,
        public readonly array $params = [],
        public readonly string|int|null $id = null,
    ) {}

    /**
     * Create a JSON-RPC 2.0 request message.
     */
    public static function request(string $method, array $params = [], string|int|null $id = null): self
    {
        return new self($method, $params, $id ?? self::generateId());
    }

    /**
     * Create a JSON-RPC 2.0 notification (no id = no response expected).
     */
    public static function notification(string $method, array $params = []): self
    {
        return new self($method, $params, null);
    }

    public function isNotification(): bool
    {
        return $this->id === null;
    }

    public function toArray(): array
    {
        $message = [
            'jsonrpc' => '2.0',
            'method' => $this->method,
        ];

        if (! empty($this->params)) {
            $message['params'] = $this->params;
        }

        if ($this->id !== null) {
            $message['id'] = $this->id;
        }

        return $message;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Parse a JSON-RPC 2.0 response.
     */
    public static function parseResponse(array $data): McpResponse
    {
        $id = $data['id'] ?? null;

        if (isset($data['error'])) {
            return new McpResponse(
                id: $id,
                result: null,
                error: new McpError(
                    code: $data['error']['code'] ?? -1,
                    message: $data['error']['message'] ?? 'Unknown error',
                    data: $data['error']['data'] ?? null,
                ),
            );
        }

        return new McpResponse(
            id: $id,
            result: $data['result'] ?? null,
            error: null,
        );
    }

    private static function generateId(): int
    {
        static $counter = 0;

        return ++$counter;
    }
}
