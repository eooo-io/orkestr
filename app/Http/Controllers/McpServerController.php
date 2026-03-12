<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectMcpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class McpServerController extends Controller
{
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
        $mcpServer->delete();

        return response()->json(['message' => 'MCP server removed.']);
    }
}
