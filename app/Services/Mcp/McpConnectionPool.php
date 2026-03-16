<?php

namespace App\Services\Mcp;

use App\Models\ProjectMcpServer;

/**
 * Connection pool for MCP servers, keyed by execution:server pair.
 *
 * Keeps process handles (stdio) and HTTP connections (SSE) alive
 * across multiple tool calls within a single agent execution run.
 * Connections auto-close after 5 minutes of inactivity.
 */
class McpConnectionPool
{
    /** @var array<string, McpClientService> */
    private static array $connections = [];

    /** @var array<string, float> */
    private static array $lastUsed = [];

    private static int $idleTimeoutSeconds = 300; // 5 minutes

    /**
     * Acquire a connection for a given execution and MCP server.
     * Returns an existing connection if one is alive, or creates a new one.
     */
    public static function acquire(string $executionId, ProjectMcpServer $server): McpClientService
    {
        $key = self::key($executionId, $server->id);

        // Return existing live connection
        if (isset(self::$connections[$key]) && self::$connections[$key]->isConnected()) {
            self::$lastUsed[$key] = microtime(true);

            return self::$connections[$key];
        }

        // Clean up dead connection if present
        if (isset(self::$connections[$key])) {
            self::$connections[$key]->disconnect();
            unset(self::$connections[$key], self::$lastUsed[$key]);
        }

        // Create and connect new client
        $client = new McpClientService;
        $client->connect($server);

        self::$connections[$key] = $client;
        self::$lastUsed[$key] = microtime(true);

        return $client;
    }

    /**
     * Release all connections for a given execution.
     */
    public static function release(string $executionId): int
    {
        $prefix = $executionId . ':';
        $released = 0;

        foreach (array_keys(self::$connections) as $key) {
            if (str_starts_with($key, $prefix)) {
                self::$connections[$key]->disconnect();
                unset(self::$connections[$key], self::$lastUsed[$key]);
                $released++;
            }
        }

        return $released;
    }

    /**
     * Release a specific connection.
     */
    public static function releaseOne(string $executionId, int $serverId): void
    {
        $key = self::key($executionId, $serverId);

        if (isset(self::$connections[$key])) {
            self::$connections[$key]->disconnect();
            unset(self::$connections[$key], self::$lastUsed[$key]);
        }
    }

    /**
     * Prune connections that have been idle longer than the timeout.
     */
    public static function pruneIdle(): int
    {
        $now = microtime(true);
        $pruned = 0;

        foreach (self::$lastUsed as $key => $lastUsed) {
            if (($now - $lastUsed) > self::$idleTimeoutSeconds) {
                if (isset(self::$connections[$key])) {
                    self::$connections[$key]->disconnect();
                    unset(self::$connections[$key]);
                }
                unset(self::$lastUsed[$key]);
                $pruned++;
            }
        }

        return $pruned;
    }

    /**
     * Check if a connection exists for an execution/server pair.
     */
    public static function has(string $executionId, int $serverId): bool
    {
        $key = self::key($executionId, $serverId);

        return isset(self::$connections[$key]) && self::$connections[$key]->isConnected();
    }

    /**
     * Get the total number of active connections in the pool.
     */
    public static function activeCount(): int
    {
        return count(self::$connections);
    }

    /**
     * Get pool status for debugging/monitoring.
     */
    public static function status(): array
    {
        $now = microtime(true);
        $statuses = [];

        foreach (self::$connections as $key => $client) {
            [$executionId, $serverId] = explode(':', $key, 2);
            $statuses[] = [
                'execution_id' => $executionId,
                'server_id' => (int) $serverId,
                'connected' => $client->isConnected(),
                'idle_seconds' => round($now - (self::$lastUsed[$key] ?? $now), 1),
            ];
        }

        return $statuses;
    }

    /**
     * Release all connections (for testing or shutdown).
     */
    public static function flush(): void
    {
        foreach (self::$connections as $client) {
            $client->disconnect();
        }

        self::$connections = [];
        self::$lastUsed = [];
    }

    /**
     * Set the idle timeout in seconds.
     */
    public static function setIdleTimeout(int $seconds): void
    {
        self::$idleTimeoutSeconds = $seconds;
    }

    private static function key(string $executionId, int $serverId): string
    {
        return "{$executionId}:{$serverId}";
    }
}
