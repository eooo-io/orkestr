<?php

namespace App\Services\Mcp;

use App\Models\ProjectMcpServer;

class McpServerManager
{
    /** @var array<int, McpClientService> */
    private array $clients = [];

    /** @var array<int, float> */
    private array $lastUsed = [];

    private int $idleTimeoutSeconds = 300; // 5 minutes

    /**
     * Get a connected client for the given MCP server.
     * Reuses existing connections when available.
     */
    public function getClient(ProjectMcpServer $server): McpClientService
    {
        $key = $server->id;

        // Return existing connection if still alive
        if (isset($this->clients[$key]) && $this->clients[$key]->isConnected()) {
            $this->lastUsed[$key] = microtime(true);

            return $this->clients[$key];
        }

        // Clean up dead connection
        if (isset($this->clients[$key])) {
            $this->clients[$key]->disconnect();
            unset($this->clients[$key], $this->lastUsed[$key]);
        }

        // Create and connect new client
        $client = new McpClientService;
        $client->connect($server);

        $this->clients[$key] = $client;
        $this->lastUsed[$key] = microtime(true);

        return $client;
    }

    /**
     * Disconnect a specific server.
     */
    public function disconnect(ProjectMcpServer $server): void
    {
        $key = $server->id;

        if (isset($this->clients[$key])) {
            $this->clients[$key]->disconnect();
            unset($this->clients[$key], $this->lastUsed[$key]);
        }
    }

    /**
     * Disconnect all servers.
     */
    public function disconnectAll(): void
    {
        foreach ($this->clients as $client) {
            $client->disconnect();
        }

        $this->clients = [];
        $this->lastUsed = [];
    }

    /**
     * Disconnect servers that have been idle longer than the timeout.
     */
    public function pruneIdle(): int
    {
        $now = microtime(true);
        $pruned = 0;

        foreach ($this->lastUsed as $key => $lastUsed) {
            if (($now - $lastUsed) > $this->idleTimeoutSeconds) {
                if (isset($this->clients[$key])) {
                    $this->clients[$key]->disconnect();
                    unset($this->clients[$key]);
                }
                unset($this->lastUsed[$key]);
                $pruned++;
            }
        }

        return $pruned;
    }

    /**
     * Check if a server is currently connected.
     */
    public function isConnected(ProjectMcpServer $server): bool
    {
        $key = $server->id;

        return isset($this->clients[$key]) && $this->clients[$key]->isConnected();
    }

    /**
     * Ping a connected server to verify it's responsive.
     */
    public function ping(ProjectMcpServer $server): bool
    {
        $key = $server->id;

        if (! isset($this->clients[$key])) {
            return false;
        }

        return $this->clients[$key]->ping();
    }

    /**
     * Get the number of active connections.
     */
    public function activeCount(): int
    {
        return count($this->clients);
    }

    /**
     * Get status info for all managed servers.
     *
     * @return array<int, array{server_id: int, connected: bool, idle_seconds: float}>
     */
    public function status(): array
    {
        $now = microtime(true);
        $statuses = [];

        foreach ($this->clients as $key => $client) {
            $statuses[] = [
                'server_id' => $key,
                'connected' => $client->isConnected(),
                'idle_seconds' => round($now - ($this->lastUsed[$key] ?? $now), 1),
            ];
        }

        return $statuses;
    }

    /**
     * Set the idle timeout in seconds.
     */
    public function setIdleTimeout(int $seconds): void
    {
        $this->idleTimeoutSeconds = $seconds;
    }

    public function __destruct()
    {
        $this->disconnectAll();
    }
}
