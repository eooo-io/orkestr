<?php

namespace App\Http\Controllers;

use App\Models\ExecutionRun;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkFeedController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => 'nullable|integer|exists:agents,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'project_id' => 'nullable|integer|exists:projects,id',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $user = Auth::user();
        $orgId = $user?->current_organization_id;

        $query = ExecutionRun::query()
            ->with(['agent.owner', 'creator'])
            ->whereIn('visibility', ['team', 'org'])
            ->whereIn('status', ['completed', 'halted_guardrail']) // don't surface running/pending
            ->orderByDesc('created_at');

        if ($orgId) {
            $query->whereIn('project_id',
                Project::where('organization_id', $orgId)->pluck('id'),
            );
        }

        if ($agentId = $validated['agent_id'] ?? null) {
            $query->where('agent_id', $agentId);
        }
        if ($userId = $validated['user_id'] ?? null) {
            $query->where('created_by', $userId);
        }
        if ($projectId = $validated['project_id'] ?? null) {
            $query->where('project_id', $projectId);
        }

        $page = $query->paginate($validated['per_page'] ?? 25);

        $items = collect($page->items())->map(fn (ExecutionRun $run) => [
            'id' => $run->id,
            'uuid' => $run->uuid,
            'status' => $run->status,
            'visibility' => $run->visibility,
            'created_at' => $run->created_at->toIso8601String(),
            'agent' => $run->agent ? [
                'id' => $run->agent->id,
                'name' => $run->agent->name,
                'slug' => $run->agent->slug,
                'icon' => $run->agent->icon,
                'owner' => $run->agent->owner ? [
                    'id' => $run->agent->owner->id,
                    'name' => $run->agent->owner->name,
                ] : null,
            ] : null,
            'creator' => $run->creator ? [
                'id' => $run->creator->id,
                'name' => $run->creator->name,
            ] : null,
            'input_summary' => $this->summarize($run->input),
            'total_tokens' => $run->total_tokens,
            'halt_reason' => $run->halt_reason,
        ])->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function fork(ExecutionRun $run): JsonResponse
    {
        if ($run->visibility === 'private' && $run->created_by !== Auth::id()) {
            abort(403, 'This run is private.');
        }

        $draft = ExecutionRun::create([
            'project_id' => $run->project_id,
            'agent_id' => $run->agent_id,
            'input' => $run->input,
            'config' => $run->config,
            'status' => 'pending',
            'visibility' => 'private',
            'created_by' => Auth::id(),
            'forked_from_run_id' => $run->id,
        ]);

        return response()->json([
            'data' => [
                'id' => $draft->id,
                'uuid' => $draft->uuid,
                'forked_from_run_id' => $run->id,
            ],
        ], 201);
    }

    public function setVisibility(Request $request, ExecutionRun $run): JsonResponse
    {
        if ($run->created_by !== Auth::id()) {
            abort(403, 'Only the creator can change visibility.');
        }

        $validated = $request->validate([
            'visibility' => 'required|string|in:private,team,org',
        ]);

        $run->update(['visibility' => $validated['visibility']]);

        return response()->json([
            'data' => ['visibility' => $run->visibility],
        ]);
    }

    protected function summarize(?array $input): string
    {
        if (! $input) return '';
        $text = (string) ($input['message'] ?? $input['goal'] ?? '');

        return mb_strimwidth($text, 0, 200, '…');
    }
}
