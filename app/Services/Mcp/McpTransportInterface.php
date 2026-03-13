<?php

namespace App\Services\Mcp;

interface McpTransportInterface
{
    /**
     * Open a connection to the MCP server.
     *
     * @throws McpConnectionException
     */
    public function connect(): void;

    /**
     * Send a JSON-RPC message and wait for a response.
     *
     * Returns null for notifications (no response expected).
     *
     * @throws McpConnectionException
     * @throws McpTimeoutException
     */
    public function send(McpMessage $message): ?McpResponse;

    /**
     * Close the connection to the MCP server.
     */
    public function disconnect(): void;

    /**
     * Check if the transport is currently connected.
     */
    public function isConnected(): bool;
}
