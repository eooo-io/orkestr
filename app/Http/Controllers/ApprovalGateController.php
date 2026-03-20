<?php

namespace App\Http\Controllers;

use App\Models\ApprovalGate;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalGateController extends Controller
{
    /**
     * GET /api/approval-gates
     * List all pending approval gates (optionally filtered by project_id).
     */
    public function index(Request $request): JsonResponse
    {
        $query = ApprovalGate::with(['project:id,name', 'agent:id,name', 'respondedBy:id,name'])
            ->orderByDesc('requested_at');

        if ($request->has('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        if ($request->input('status', 'pending') === 'pending') {
            $query->pending();
        }

        $gates = $query->paginate($request->input('per_page', 25));

        return response()->json([
            'data' => collect($gates->items())->map(fn ($g) => $this->formatGate($g)),
            'meta' => [
                'current_page' => $gates->currentPage(),
                'last_page' => $gates->lastPage(),
                'total' => $gates->total(),
            ],
        ]);
    }

    /**
     * GET /api/projects/{project}/approval-gates
     */
    public function projectIndex(Project $project): JsonResponse
    {
        $gates = ApprovalGate::where('project_id', $project->id)
            ->with(['agent:id,name', 'respondedBy:id,name'])
            ->orderByDesc('requested_at')
            ->get()
            ->map(fn ($g) => $this->formatGate($g));

        return response()->json(['data' => $gates]);
    }

    /**
     * GET /api/approval-gates/{approval_gate}
     */
    public function show(ApprovalGate $approvalGate): JsonResponse
    {
        $approvalGate->load(['project:id,name', 'agent:id,name', 'executionRun', 'respondedBy:id,name']);

        return response()->json(['data' => $this->formatGate($approvalGate)]);
    }

    /**
     * POST /api/approval-gates/{approval_gate}/approve
     */
    public function approve(Request $request, ApprovalGate $approvalGate): JsonResponse
    {
        if ($approvalGate->status !== 'pending') {
            return response()->json(['message' => 'Gate is not pending'], 422);
        }

        $approvalGate->update([
            'status' => 'approved',
            'responded_by' => $request->user()?->id,
            'responded_at' => now(),
            'response_note' => $request->input('response_note'),
        ]);

        return response()->json(['data' => $this->formatGate($approvalGate)]);
    }

    /**
     * POST /api/approval-gates/{approval_gate}/reject
     */
    public function reject(Request $request, ApprovalGate $approvalGate): JsonResponse
    {
        if ($approvalGate->status !== 'pending') {
            return response()->json(['message' => 'Gate is not pending'], 422);
        }

        $approvalGate->update([
            'status' => 'rejected',
            'responded_by' => $request->user()?->id,
            'responded_at' => now(),
            'response_note' => $request->input('response_note'),
        ]);

        return response()->json(['data' => $this->formatGate($approvalGate)]);
    }

    /**
     * POST /api/approval-gates/{approval_gate}/extend
     */
    public function extend(Request $request, ApprovalGate $approvalGate): JsonResponse
    {
        if ($approvalGate->status !== 'pending') {
            return response()->json(['message' => 'Gate is not pending'], 422);
        }

        $validated = $request->validate([
            'minutes' => 'required|integer|min:1|max:10080', // max 7 days
        ]);

        $newExpiry = ($approvalGate->expires_at ?? now())->addMinutes($validated['minutes']);

        $approvalGate->update([
            'expires_at' => $newExpiry,
        ]);

        return response()->json(['data' => $this->formatGate($approvalGate)]);
    }

    private function formatGate(ApprovalGate $gate): array
    {
        return [
            'id' => $gate->id,
            'uuid' => $gate->uuid,
            'execution_run_id' => $gate->execution_run_id,
            'agent_id' => $gate->agent_id,
            'agent' => $gate->relationLoaded('agent') && $gate->agent ? [
                'id' => $gate->agent->id,
                'name' => $gate->agent->name,
            ] : null,
            'project_id' => $gate->project_id,
            'project' => $gate->relationLoaded('project') && $gate->project ? [
                'id' => $gate->project->id,
                'name' => $gate->project->name,
            ] : null,
            'type' => $gate->type,
            'title' => $gate->title,
            'description' => $gate->description,
            'context' => $gate->context,
            'status' => $gate->status,
            'requested_at' => $gate->requested_at?->toIso8601String(),
            'responded_at' => $gate->responded_at?->toIso8601String(),
            'expires_at' => $gate->expires_at?->toIso8601String(),
            'responded_by' => $gate->relationLoaded('respondedBy') && $gate->respondedBy ? [
                'id' => $gate->respondedBy->id,
                'name' => $gate->respondedBy->name,
            ] : $gate->responded_by,
            'response_note' => $gate->response_note,
            'auto_approve_after_minutes' => $gate->auto_approve_after_minutes,
            'created_at' => $gate->created_at?->toIso8601String(),
            'updated_at' => $gate->updated_at?->toIso8601String(),
        ];
    }
}
