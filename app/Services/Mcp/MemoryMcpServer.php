<?php

namespace App\Services\Mcp;

/**
 * Defines MCP tool specifications for the agent memory system.
 *
 * These tool definitions are injected into the agent's available tools at compose time
 * when the agent has `memory_enabled=true`. The actual execution routes through
 * AgentMemoryService via the ToolDispatcher.
 */
class MemoryMcpServer
{
    /**
     * Get the MCP tool definitions for memory operations.
     *
     * @return array<int, array{name: string, description: string, input_schema: array}>
     */
    public static function toolDefinitions(): array
    {
        return [
            [
                'name' => 'memory_remember',
                'description' => 'Store a memory that will persist across conversations. Use this to save important facts, user preferences, decisions, or any information that should be remembered for future interactions.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => [
                            'type' => 'string',
                            'description' => 'A unique identifier/slug for this memory (e.g. "user_preference_language", "project_deadline")',
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'The content/fact to remember',
                        ],
                    ],
                    'required' => ['key', 'content'],
                ],
            ],
            [
                'name' => 'memory_recall',
                'description' => 'Search your memories for information relevant to a query. Returns the most relevant stored memories using semantic search.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'The search query to find relevant memories',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of memories to return (default: 5)',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'memory_forget',
                'description' => 'Remove a previously stored memory by its key.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => [
                            'type' => 'string',
                            'description' => 'The key of the memory to forget',
                        ],
                    ],
                    'required' => ['key'],
                ],
            ],
        ];
    }
}
