<?php

namespace App\Services\Mcp;

use App\Models\ProjectMcpServer;

class McpClientService
{
    private ?McpTransportInterface $transport = null;

    /** @var McpToolDefinition[] */
    private array $cachedTools = [];

    private bool $initialized = false;

    private ?ProjectMcpServer $server = null;

    /**
     * Connect to an MCP server using its configured transport.
     */
    public function connect(ProjectMcpServer $server): void
    {
        $this->disconnect();
        $this->server = $server;

        $this->transport = $this->createTransport($server);
        $this->transport->connect();

        $this->initialize();
    }

    /**
     * Connect using a pre-built transport (useful for testing).
     */
    public function connectWithTransport(McpTransportInterface $transport): void
    {
        $this->disconnect();
        $this->transport = $transport;
        $this->transport->connect();
        $this->initialize();
    }

    /**
     * List available tools from the connected MCP server.
     *
     * @return McpToolDefinition[]
     */
    public function listTools(): array
    {
        $this->ensureConnected();

        if (! empty($this->cachedTools)) {
            return $this->cachedTools;
        }

        $response = $this->transport->send(
            McpMessage::request('tools/list')
        );

        if ($response->isError()) {
            throw new McpConnectionException("Failed to list tools: {$response->error->message}");
        }

        $tools = $response->result['tools'] ?? [];

        $this->cachedTools = array_map(
            fn (array $tool) => McpToolDefinition::fromArray($tool),
            $tools,
        );

        return $this->cachedTools;
    }

    /**
     * Call a tool on the connected MCP server.
     */
    public function callTool(string $name, array $arguments = []): McpToolResult
    {
        $this->ensureConnected();

        $response = $this->transport->send(
            McpMessage::request('tools/call', [
                'name' => $name,
                'arguments' => $arguments,
            ])
        );

        return McpToolResult::fromResponse($response);
    }

    /**
     * Send a ping to verify the server is responsive.
     */
    public function ping(): bool
    {
        if (! $this->isConnected()) {
            return false;
        }

        try {
            $response = $this->transport->send(McpMessage::request('ping'));

            return $response->isSuccess();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Disconnect from the current MCP server.
     */
    public function disconnect(): void
    {
        if ($this->transport) {
            $this->transport->disconnect();
        }

        $this->transport = null;
        $this->cachedTools = [];
        $this->initialized = false;
        $this->server = null;
    }

    /**
     * Check if currently connected to a server.
     */
    public function isConnected(): bool
    {
        return $this->transport !== null && $this->transport->isConnected();
    }

    /**
     * Get the currently connected server.
     */
    public function getServer(): ?ProjectMcpServer
    {
        return $this->server;
    }

    /**
     * Clear the cached tools list, forcing a fresh fetch on next listTools().
     */
    public function clearToolCache(): void
    {
        $this->cachedTools = [];
    }

    private function createTransport(ProjectMcpServer $server): McpTransportInterface
    {
        return match ($server->transport) {
            'stdio' => new StdioTransport(
                command: $server->command,
                args: $server->args ?? [],
                env: $server->env ?? [],
            ),
            'sse', 'streamable-http' => new SseTransport(
                url: $server->url,
                headers: $server->headers ?? [],
            ),
            default => throw new McpConnectionException("Unsupported MCP transport: {$server->transport}"),
        };
    }

    private function initialize(): void
    {
        $response = $this->transport->send(
            McpMessage::request('initialize', [
                'protocolVersion' => '2024-11-05',
                'capabilities' => new \stdClass,
                'clientInfo' => [
                    'name' => 'agentis-studio',
                    'version' => '1.0.0',
                ],
            ])
        );

        if ($response->isError()) {
            $this->disconnect();
            throw new McpConnectionException("MCP initialization failed: {$response->error->message}");
        }

        // Send initialized notification
        $this->transport->send(
            McpMessage::notification('notifications/initialized')
        );

        $this->initialized = true;
    }

    private function ensureConnected(): void
    {
        if (! $this->isConnected()) {
            throw new McpConnectionException('Not connected to any MCP server');
        }

        if (! $this->initialized) {
            throw new McpConnectionException('MCP connection not initialized');
        }
    }
}
