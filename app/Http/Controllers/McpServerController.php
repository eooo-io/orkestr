<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectMcpServer;
use App\Services\Mcp\McpConnectionException;
use App\Services\Mcp\McpServerManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class McpServerController extends Controller
{
    public function __construct(
        private McpServerManager $serverManager,
    ) {}
    public function index(Project $project): JsonResponse
    {
        return response()->json([
            'mcp_servers' => $project->mcpServers()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'transport' => 'required|in:stdio,sse,streamable-http',
            'command' => 'nullable|required_if:transport,stdio|string',
            'args' => 'nullable|array',
            'url' => 'nullable|required_unless:transport,stdio|string',
            'env' => 'nullable|array',
            'headers' => 'nullable|array',
            'enabled' => 'boolean',
        ]);

        $server = $project->mcpServers()->create($validated);

        return response()->json(['mcp_server' => $server], 201);
    }

    public function update(Request $request, ProjectMcpServer $mcpServer): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'transport' => 'sometimes|in:stdio,sse,streamable-http',
            'command' => 'nullable|string',
            'args' => 'nullable|array',
            'url' => 'nullable|string',
            'env' => 'nullable|array',
            'headers' => 'nullable|array',
            'enabled' => 'boolean',
        ]);

        $mcpServer->update($validated);

        return response()->json(['mcp_server' => $mcpServer->fresh()]);
    }

    public function destroy(ProjectMcpServer $mcpServer): JsonResponse
    {
        $this->serverManager->disconnect($mcpServer);
        $mcpServer->delete();

        return response()->json(['message' => 'MCP server removed.']);
    }

    /**
     * GET /api/projects/{project}/mcp-servers/{mcpServer}/tools
     *
     * Connect to the MCP server and list its available tools.
     */
    public function tools(Project $project, ProjectMcpServer $mcpServer): JsonResponse
    {
        if ($mcpServer->project_id !== $project->id) {
            abort(404, 'MCP server not found in this project.');
        }

        try {
            $client = $this->serverManager->getClient($mcpServer);
            $tools = $client->listTools();

            return response()->json([
                'tools' => array_map(fn ($tool) => $tool->toArray(), $tools),
                'server' => [
                    'id' => $mcpServer->id,
                    'name' => $mcpServer->name,
                    'transport' => $mcpServer->transport,
                    'connected' => true,
                ],
            ]);
        } catch (McpConnectionException $e) {
            return response()->json([
                'tools' => [],
                'server' => [
                    'id' => $mcpServer->id,
                    'name' => $mcpServer->name,
                    'transport' => $mcpServer->transport,
                    'connected' => false,
                ],
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    /**
     * POST /api/projects/{project}/mcp-servers/{mcpServer}/ping
     *
     * Check if an MCP server is reachable and responsive.
     */
    public function ping(Project $project, ProjectMcpServer $mcpServer): JsonResponse
    {
        if ($mcpServer->project_id !== $project->id) {
            abort(404, 'MCP server not found in this project.');
        }

        try {
            $client = $this->serverManager->getClient($mcpServer);
            $alive = $client->ping();

            return response()->json([
                'connected' => $alive,
                'server_id' => $mcpServer->id,
            ]);
        } catch (McpConnectionException $e) {
            return response()->json([
                'connected' => false,
                'server_id' => $mcpServer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
