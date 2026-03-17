<?php

namespace App\Services\Mcp;

class KnowledgeMcpTools
{
    /**
     * Get the MCP tool definitions for knowledge operations.
     * Injected into agents that have knowledge_access=true.
     *
     * @return McpToolDefinition[]
     */
    public static function definitions(): array
    {
        return [
            new McpToolDefinition(
                name: 'store_knowledge',
                description: 'Store structured data in the agent knowledge base under a namespace and key.',
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => [
                            'type' => 'string',
                            'description' => 'Namespace for grouping knowledge entries (e.g. facts, preferences, patterns, contacts, history).',
                        ],
                        'key' => [
                            'type' => 'string',
                            'description' => 'Unique key within the namespace.',
                        ],
                        'value' => [
                            'description' => 'The data to store (any JSON-serializable value).',
                        ],
                    ],
                    'required' => ['namespace', 'key', 'value'],
                ],
            ),
            new McpToolDefinition(
                name: 'query_knowledge',
                description: 'Retrieve knowledge entries by namespace and optionally by key.',
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => [
                            'type' => 'string',
                            'description' => 'Namespace to query.',
                        ],
                        'key' => [
                            'type' => 'string',
                            'description' => 'Optional specific key to retrieve.',
                        ],
                    ],
                    'required' => ['namespace'],
                ],
            ),
            new McpToolDefinition(
                name: 'search_knowledge',
                description: 'Full-text search across all knowledge entries for the agent.',
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Search query string.',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ),
            new McpToolDefinition(
                name: 'delete_knowledge',
                description: 'Remove a knowledge entry by namespace and key.',
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => [
                            'type' => 'string',
                            'description' => 'Namespace of the entry to delete.',
                        ],
                        'key' => [
                            'type' => 'string',
                            'description' => 'Key of the entry to delete.',
                        ],
                    ],
                    'required' => ['namespace', 'key'],
                ],
            ),
        ];
    }

    /**
     * Return all definitions as arrays (for JSON serialization).
     */
    public static function toArray(): array
    {
        return array_map(fn (McpToolDefinition $def) => $def->toArray(), self::definitions());
    }
}
