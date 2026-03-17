<?php

namespace App\Services\Mcp;

class DocumentMcpTools
{
    /**
     * Get the MCP tool definitions for document operations.
     * Injected into agents that have document_access=true.
     *
     * @return McpToolDefinition[]
     */
    public static function definitions(): array
    {
        return [
            new McpToolDefinition(
                name: 'read_document',
                description: 'Read the content of a document stored in the project file store.',
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'path' => [
                            'type' => 'string',
                            'description' => 'Relative path to the document within the project scope.',
                        ],
                    ],
                    'required' => ['path'],
                ],
            ),
            new McpToolDefinition(
                name: 'write_document',
                description: 'Write or overwrite a document in the project file store.',
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'path' => [
                            'type' => 'string',
                            'description' => 'Relative path to the document within the project scope.',
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'The content to write to the file.',
                        ],
                    ],
                    'required' => ['path', 'content'],
                ],
            ),
            new McpToolDefinition(
                name: 'list_documents',
                description: 'List documents in the project file store, optionally filtered by a path prefix.',
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'prefix' => [
                            'type' => 'string',
                            'description' => 'Optional path prefix to filter results.',
                        ],
                    ],
                    'required' => [],
                ],
            ),
            new McpToolDefinition(
                name: 'delete_document',
                description: 'Delete a document from the project file store.',
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'path' => [
                            'type' => 'string',
                            'description' => 'Relative path to the document to delete.',
                        ],
                    ],
                    'required' => ['path'],
                ],
            ),
        ];
    }

    /**
     * Build the scoped storage path for agent documents.
     */
    public static function scopedPath(string $agentSlug, int $projectId, string $relativePath): string
    {
        $clean = ltrim(str_replace('..', '', $relativePath), '/');

        return "agents/{$agentSlug}/projects/{$projectId}/{$clean}";
    }

    /**
     * Build the scoped prefix for listing documents.
     */
    public static function scopedPrefix(string $agentSlug, int $projectId, ?string $prefix = null): string
    {
        $base = "agents/{$agentSlug}/projects/{$projectId}";

        if ($prefix) {
            $clean = ltrim(str_replace('..', '', $prefix), '/');
            $base .= "/{$clean}";
        }

        return $base;
    }

    /**
     * Return all definitions as arrays (for JSON serialization).
     */
    public static function toArray(): array
    {
        return array_map(fn (McpToolDefinition $def) => $def->toArray(), self::definitions());
    }
}
