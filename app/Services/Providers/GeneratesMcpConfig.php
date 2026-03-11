<?php

namespace App\Services\Providers;

use App\Models\Project;

trait GeneratesMcpConfig
{
    /**
     * Generate the standard mcpServers JSON config from project MCP servers.
     *
     * All major providers (Claude, Cursor, Copilot, Windsurf, Cline, OpenClaw)
     * use the same { "mcpServers": { ... } } format — just at different file paths.
     *
     * @return array<string, string>  Map of file path => JSON content (empty if no servers)
     */
    protected function generateMcpFiles(Project $project, string $outputPath): array
    {
        $servers = $project->mcpServers()->where('enabled', true)->get();

        if ($servers->isEmpty()) {
            return [];
        }

        $mcpServers = [];

        foreach ($servers as $server) {
            $entry = [];

            if ($server->transport === 'stdio') {
                if ($server->command) {
                    $entry['command'] = $server->command;
                }
                if (! empty($server->args)) {
                    $entry['args'] = $server->args;
                }
            } else {
                // SSE or streamable-http
                if ($server->url) {
                    $entry['url'] = $server->url;
                }
                if (! empty($server->headers)) {
                    $entry['headers'] = $server->headers;
                }
            }

            if (! empty($server->env)) {
                $entry['env'] = $server->env;
            }

            $mcpServers[$server->name] = $entry;
        }

        $json = json_encode(
            ['mcpServers' => $mcpServers],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        );

        return [$outputPath => $json . "\n"];
    }
}
