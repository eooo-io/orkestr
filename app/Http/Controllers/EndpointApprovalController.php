<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectA2aAgent;
use App\Models\ProjectMcpServer;
use App\Services\EndpointApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EndpointApprovalController extends Controller
{
    public function __construct(
        private readonly EndpointApprovalService $approvalService,
    ) {}

    /**
     * List pending endpoint approvals for a project.
     */
    public function index(Project $project): JsonResponse
    {
        $pending = $this->approvalService->getPendingApprovals($project->id);

        return response()->json([
            'mcp_servers' => $pending['mcp_servers'],
            'a2a_agents' => $pending['a2a_agents'],
        ]);
    }

    /**
     * Approve an endpoint.
     */
    public function approve(Request $request, string $type, int $id): JsonResponse
    {
        $endpoint = $this->resolveEndpoint($type, $id);

        if (! $endpoint) {
            return response()->json(['error' => 'Endpoint not found'], 404);
        }

        if ($endpoint->approval_status === 'approved') {
            return response()->json(['message' => 'Endpoint is already approved']);
        }

        $this->approvalService->approve($endpoint, $request->user()->id);

        return response()->json([
            'message' => 'Endpoint approved',
            'endpoint' => $endpoint->fresh(),
        ]);
    }

    /**
     * Reject an endpoint.
     */
    public function reject(Request $request, string $type, int $id): JsonResponse
    {
        $endpoint = $this->resolveEndpoint($type, $id);

        if (! $endpoint) {
            return response()->json(['error' => 'Endpoint not found'], 404);
        }

        if ($endpoint->approval_status === 'rejected') {
            return response()->json(['message' => 'Endpoint is already rejected']);
        }

        $this->approvalService->reject($endpoint, $request->user()->id);

        return response()->json([
            'message' => 'Endpoint rejected',
            'endpoint' => $endpoint->fresh(),
        ]);
    }

    /**
     * Resolve the endpoint model by type and ID.
     */
    private function resolveEndpoint(string $type, int $id): ProjectMcpServer|ProjectA2aAgent|null
    {
        return match ($type) {
            'mcp' => ProjectMcpServer::find($id),
            'a2a' => ProjectA2aAgent::find($id),
            default => null,
        };
    }
}
