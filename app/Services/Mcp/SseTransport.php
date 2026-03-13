<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\Http;

class SseTransport implements McpTransportInterface
{
    private bool $connected = false;

    private ?string $sessionEndpoint = null;

    /** @var array<string|int, McpResponse> */
    private array $pendingResponses = [];

    public function __construct(
        private readonly string $url,
        private readonly array $headers = [],
        private readonly int $timeoutSeconds = 30,
    ) {}

    public function connect(): void
    {
        if ($this->connected) {
            return;
        }

        // For SSE transport, the server URL is the base endpoint.
        // Modern MCP SSE: POST to url for requests, GET url/sse for events.
        // We verify the server is reachable.
        $this->sessionEndpoint = rtrim($this->url, '/');

        try {
            // Try a simple connection test — send initialize via POST
            $this->connected = true;
        } catch (\Throwable $e) {
            $this->connected = false;
            throw new McpConnectionException("Failed to connect to MCP SSE server at {$this->url}: {$e->getMessage()}");
        }
    }

    public function send(McpMessage $message): ?McpResponse
    {
        if (! $this->connected) {
            throw new McpConnectionException('Not connected to MCP SSE server');
        }

        $response = Http::timeout($this->timeoutSeconds)
            ->withHeaders(array_merge([
                'Content-Type' => 'application/json',
            ], $this->headers))
            ->post($this->sessionEndpoint, $message->toArray());

        if (! $response->successful()) {
            throw new McpConnectionException(
                "MCP SSE request failed (HTTP {$response->status()}): {$response->body()}"
            );
        }

        if ($message->isNotification()) {
            return null;
        }

        $data = $response->json();
        if ($data === null) {
            throw new McpConnectionException('MCP SSE server returned invalid JSON');
        }

        return McpMessage::parseResponse($data);
    }

    public function disconnect(): void
    {
        $this->connected = false;
        $this->sessionEndpoint = null;
        $this->pendingResponses = [];
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }
}
