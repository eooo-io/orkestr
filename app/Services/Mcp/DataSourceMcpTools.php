<?php

namespace App\Services\Mcp;

use App\Models\DataSource;

class DataSourceMcpTools
{
    /**
     * Generate MCP tool definitions for a given data source.
     *
     * @return McpToolDefinition[]
     */
    public static function forDataSource(DataSource $ds): array
    {
        return match ($ds->type) {
            'postgres', 'mysql' => self::databaseTools($ds),
            'minio', 's3' => self::objectStorageTools($ds),
            'filesystem' => self::filesystemTools($ds),
            'redis' => self::redisTools($ds),
            default => [],
        };
    }

    /**
     * Return all definitions as arrays for JSON serialization.
     */
    public static function toArray(DataSource $ds): array
    {
        return array_map(
            fn (McpToolDefinition $def) => $def->toArray(),
            self::forDataSource($ds),
        );
    }

    /**
     * PostgreSQL / MySQL tools.
     */
    private static function databaseTools(DataSource $ds): array
    {
        $tools = [
            new McpToolDefinition(
                name: "ds_{$ds->id}_query",
                description: "Execute a SQL query against the '{$ds->name}' {$ds->type} database." .
                    ($ds->isReadOnly() ? ' Only SELECT queries are allowed.' : ''),
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'sql' => [
                            'type' => 'string',
                            'description' => 'The SQL query to execute.' .
                                ($ds->isReadOnly() ? ' Must be a SELECT statement.' : ''),
                        ],
                    ],
                    'required' => ['sql'],
                ],
            ),
        ];

        if (! $ds->isReadOnly()) {
            $tools[] = new McpToolDefinition(
                name: "ds_{$ds->id}_execute",
                description: "Execute a write SQL statement (INSERT/UPDATE/DELETE) against '{$ds->name}'.",
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'sql' => [
                            'type' => 'string',
                            'description' => 'The SQL statement to execute.',
                        ],
                    ],
                    'required' => ['sql'],
                ],
            );
        }

        return $tools;
    }

    /**
     * MinIO / S3 object storage tools.
     */
    private static function objectStorageTools(DataSource $ds): array
    {
        $tools = [
            new McpToolDefinition(
                name: "ds_{$ds->id}_read_document",
                description: "Read a document from the '{$ds->name}' object store.",
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'path' => [
                            'type' => 'string',
                            'description' => 'Path to the object within the bucket.',
                        ],
                    ],
                    'required' => ['path'],
                ],
            ),
            new McpToolDefinition(
                name: "ds_{$ds->id}_list_documents",
                description: "List documents in the '{$ds->name}' object store.",
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'prefix' => [
                            'type' => 'string',
                            'description' => 'Optional prefix to filter results.',
                        ],
                    ],
                    'required' => [],
                ],
            ),
        ];

        if (! $ds->isReadOnly()) {
            $tools[] = new McpToolDefinition(
                name: "ds_{$ds->id}_write_document",
                description: "Write a document to the '{$ds->name}' object store.",
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'path' => [
                            'type' => 'string',
                            'description' => 'Path for the object within the bucket.',
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'The content to write.',
                        ],
                    ],
                    'required' => ['path', 'content'],
                ],
            );
        }

        return $tools;
    }

    /**
     * Filesystem tools.
     */
    private static function filesystemTools(DataSource $ds): array
    {
        $tools = [
            new McpToolDefinition(
                name: "ds_{$ds->id}_read_file",
                description: "Read a file from the '{$ds->name}' filesystem.",
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'path' => [
                            'type' => 'string',
                            'description' => 'Relative path to the file.',
                        ],
                    ],
                    'required' => ['path'],
                ],
            ),
            new McpToolDefinition(
                name: "ds_{$ds->id}_list_files",
                description: "List files in the '{$ds->name}' filesystem.",
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'directory' => [
                            'type' => 'string',
                            'description' => 'Optional directory path to list.',
                        ],
                    ],
                    'required' => [],
                ],
            ),
        ];

        if (! $ds->isReadOnly()) {
            $tools[] = new McpToolDefinition(
                name: "ds_{$ds->id}_write_file",
                description: "Write a file to the '{$ds->name}' filesystem.",
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'path' => [
                            'type' => 'string',
                            'description' => 'Relative path for the file.',
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'The content to write.',
                        ],
                    ],
                    'required' => ['path', 'content'],
                ],
            );
        }

        return $tools;
    }

    /**
     * Redis tools.
     */
    private static function redisTools(DataSource $ds): array
    {
        $tools = [
            new McpToolDefinition(
                name: "ds_{$ds->id}_get",
                description: "Get a value by key from the '{$ds->name}' Redis store.",
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'key' => [
                            'type' => 'string',
                            'description' => 'The key to retrieve.',
                        ],
                    ],
                    'required' => ['key'],
                ],
            ),
            new McpToolDefinition(
                name: "ds_{$ds->id}_keys",
                description: "List keys matching a pattern in the '{$ds->name}' Redis store.",
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'pattern' => [
                            'type' => 'string',
                            'description' => 'Glob-style pattern to match keys (e.g. "user:*").',
                        ],
                    ],
                    'required' => ['pattern'],
                ],
            ),
        ];

        if (! $ds->isReadOnly()) {
            $tools[] = new McpToolDefinition(
                name: "ds_{$ds->id}_set",
                description: "Set a key-value pair in the '{$ds->name}' Redis store.",
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'key' => [
                            'type' => 'string',
                            'description' => 'The key to set.',
                        ],
                        'value' => [
                            'type' => 'string',
                            'description' => 'The value to store.',
                        ],
                    ],
                    'required' => ['key', 'value'],
                ],
            );
        }

        return $tools;
    }
}
