<?php

namespace App\Services;

use App\Models\ProjectA2aAgent;
use App\Models\ProjectMcpServer;
use Illuminate\Database\Eloquent\Model;

class EndpointApprovalService
{
    /**
     * Check if an endpoint URL requires approval (not yet seen in this org).
     */
    public function requiresApproval(string $url, ?int $organizationId = null): bool
    {
        // Check if any MCP server with this URL has been approved in the org
        $mcpApproved = ProjectMcpServer::where('url', $url)
            ->where('approval_status', 'approved')
            ->when($organizationId, function ($query, $orgId) {
                $query->whereHas('project', fn ($q) => $q->where('organization_id', $orgId));
            })
            ->exists();

        if ($mcpApproved) {
            return false;
        }

        // Check if any A2A agent with this URL has been approved in the org
        $a2aApproved = ProjectA2aAgent::where('url', $url)
            ->where('approval_status', 'approved')
            ->when($organizationId, function ($query, $orgId) {
                $query->whereHas('project', fn ($q) => $q->where('organization_id', $orgId));
            })
            ->exists();

        return ! $a2aApproved;
    }

    /**
     * Approve an endpoint (MCP server or A2A agent).
     */
    public function approve(Model $endpoint, int $userId): void
    {
        $endpoint->update([
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $userId,
        ]);
    }

    /**
     * Reject an endpoint (MCP server or A2A agent).
     */
    public function reject(Model $endpoint, int $userId): void
    {
        $endpoint->update([
            'approval_status' => 'rejected',
            'approved_at' => null,
            'approved_by' => $userId,
        ]);
    }

    /**
     * Get all pending endpoint approvals for a project.
     *
     * @return array{mcp_servers: \Illuminate\Support\Collection, a2a_agents: \Illuminate\Support\Collection}
     */
    public function getPendingApprovals(int $projectId): array
    {
        $mcpServers = ProjectMcpServer::where('project_id', $projectId)
            ->where('approval_status', 'pending')
            ->get();

        $a2aAgents = ProjectA2aAgent::where('project_id', $projectId)
            ->where('approval_status', 'pending')
            ->get();

        return [
            'mcp_servers' => $mcpServers,
            'a2a_agents' => $a2aAgents,
        ];
    }
}
