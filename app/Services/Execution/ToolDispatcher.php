<?php

namespace App\Services\Execution;

use App\Models\ProjectA2aAgent;
use App\Models\ProjectMcpServer;
use App\Services\A2a\A2aClientService;
use App\Services\Mcp\McpServerManager;
use App\Services\Mcp\McpToolResult;

class ToolDispatcher
{
    /** @var array<string, array{type: string, server_id?: int}> */
    private array $toolRegistry = [];

    public function __construct(
        private McpServerManager $serverManager,
        private ?A2aClientService $a2aClient = null,
    ) {
        $this->a2aClient ??= app(A2aClientService::class);
    }

    /**
     * Register MCP server tools in the dispatcher.
     *
     * @param  ProjectMcpServer[]  $servers
     */
    public function registerMcpServers(array $servers): void
    {
        foreach ($servers as $server) {
            try {
                $client = $this->serverManager->getClient($server);
                $tools = $client->listTools();

                foreach ($tools as $tool) {
                    $this->toolRegistry[$tool->name] = [
                        'type' => 'mcp',
                        'server_id' => $server->id,
                    ];
                }
            } catch (\Throwable $e) {
                // Server unavailable — skip its tools
                \Log::warning("Failed to register MCP tools from {$server->name}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Register A2A agent tools.
     *
     * @param  ProjectA2aAgent[]  $agents
     */
    public function registerA2aAgents(array $agents): void
    {
        foreach ($agents as $agent) {
            $toolName = 'a2a_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $agent->name);
            $this->toolRegistry[$toolName] = [
                'type' => 'a2a',
                'agent_id' => $agent->id,
                'description' => $agent->description ?? "Delegate task to {$agent->name}",
                'skills' => $agent->skills ?? [],
            ];
        }
    }

    /**
     * Dispatch a tool call to the appropriate handler.
     */
    public function dispatch(string $toolName, array $arguments): ToolCallResult
    {
        $startTime = microtime(true);

        $registration = $this->toolRegistry[$toolName] ?? null;

        if ($registration === null) {
            return new ToolCallResult(
                toolName: $toolName,
                content: [['type' => 'text', 'text' => "Unknown tool: {$toolName}"]],
                isError: true,
                durationMs: 0,
            );
        }

        try {
            $result = match ($registration['type']) {
                'mcp' => $this->dispatchMcp($registration['server_id'], $toolName, $arguments),
                'a2a' => $this->dispatchA2a($registration['agent_id'], $toolName, $arguments),
                default => new McpToolResult(
                    content: [['type' => 'text', 'text' => "Unsupported tool type: {$registration['type']}"]],
                    isError: true,
                ),
            };

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            return new ToolCallResult(
                toolName: $toolName,
                content: $result->content,
                isError: $result->isError,
                durationMs: $durationMs,
            );
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            return new ToolCallResult(
                toolName: $toolName,
                content: [['type' => 'text', 'text' => "Tool execution error: {$e->getMessage()}"]],
                isError: true,
                durationMs: $durationMs,
            );
        }
    }

    /**
     * Dispatch multiple tool calls in parallel (sequential for now, parallel in future).
     *
     * @return ToolCallResult[]
     */
    public function dispatchMany(array $toolCalls): array
    {
        return array_map(
            fn (array $call) => $this->dispatch($call['name'], $call['input'] ?? []),
            $toolCalls,
        );
    }

    /**
     * Get all registered tool names.
     *
     * @return string[]
     */
    public function registeredTools(): array
    {
        return array_keys($this->toolRegistry);
    }

    /**
     * Build tool definitions in Anthropic API format for LLM calls.
     */
    public function getToolDefinitions(): array
    {
        $definitions = [];

        foreach ($this->toolRegistry as $name => $reg) {
            if ($reg['type'] === 'mcp') {
                $server = ProjectMcpServer::find($reg['server_id']);
                if ($server) {
                    try {
                        $client = $this->serverManager->getClient($server);
                        foreach ($client->listTools() as $tool) {
                            if ($tool->name === $name) {
                                $definitions[] = [
                                    'name' => $tool->name,
                                    'description' => $tool->description,
                                    'input_schema' => $tool->inputSchema ?: ['type' => 'object'],
                                ];
                                break;
                            }
                        }
                    } catch (\Throwable) {
                        // Skip unavailable servers
                    }
                }
            } elseif ($reg['type'] === 'a2a') {
                $skillsDesc = ! empty($reg['skills']) ? ' Skills: ' . implode(', ', $reg['skills']) : '';
                $definitions[] = [
                    'name' => $name,
                    'description' => ($reg['description'] ?? "Delegate task to A2A agent") . '.' . $skillsDesc,
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'message' => [
                                'type' => 'string',
                                'description' => 'The task or message to send to the agent',
                            ],
                        ],
                        'required' => ['message'],
                    ],
                ];
            }
        }

        return $definitions;
    }

    private function dispatchMcp(int $serverId, string $toolName, array $arguments): McpToolResult
    {
        $server = ProjectMcpServer::findOrFail($serverId);
        $client = $this->serverManager->getClient($server);

        return $client->callTool($toolName, $arguments);
    }

    private function dispatchA2a(int $agentId, string $toolName, array $arguments): McpToolResult
    {
        $agent = ProjectA2aAgent::find($agentId);
        if (! $agent) {
            return new McpToolResult(
                content: [['type' => 'text', 'text' => "A2A agent not found: {$agentId}"]],
                isError: true,
            );
        }

        $message = $arguments['message'] ?? json_encode($arguments);

        $result = $this->a2aClient->delegateTask($agent, $message);

        return new McpToolResult(
            content: [['type' => 'text', 'text' => $result->text()]],
            isError: $result->isFailed(),
        );
    }
}
