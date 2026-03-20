<?php

namespace App\Http\Controllers;

use App\Models\AgentTask;
use App\Models\NegotiationLog;
use App\Models\Project;
use App\Models\TaskBid;
use App\Models\TeamFormation;
use App\Services\Negotiation\AdvertisementService;
use App\Services\Negotiation\BiddingService;
use App\Services\Negotiation\TeamFormationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NegotiationController extends Controller
{
    public function __construct(
        protected BiddingService $biddingService,
        protected AdvertisementService $advertisementService,
        protected TeamFormationService $teamFormationService,
    ) {}

    /**
     * POST /api/negotiation/bids
     * Open bidding on a task.
     */
    public function openBidding(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_id' => 'required|integer|exists:agent_tasks,id',
            'project_id' => 'required|integer|exists:projects,id',
        ]);

        $task = AgentTask::findOrFail($validated['task_id']);
        $bids = $this->biddingService->openBidding($task, $validated['project_id']);

        return response()->json([
            'data' => $bids->map(fn (TaskBid $bid) => $this->formatBid($bid)),
            'count' => $bids->count(),
        ]);
    }

    /**
     * GET /api/negotiation/tasks/{taskId}/bids
     * List bids for a task.
     */
    public function bids(int $taskId): JsonResponse
    {
        $bids = TaskBid::where('task_id', $taskId)
            ->with('agent:id,name,slug,icon')
            ->orderByDesc('bid_score')
            ->get()
            ->map(fn (TaskBid $bid) => $this->formatBid($bid));

        return response()->json(['data' => $bids]);
    }

    /**
     * POST /api/negotiation/bids/{taskBid}/accept
     * Accept a bid.
     */
    public function acceptBid(TaskBid $taskBid): JsonResponse
    {
        if ($taskBid->status !== 'pending') {
            return response()->json(['message' => 'Bid is not pending'], 422);
        }

        $task = $this->biddingService->acceptBid($taskBid);

        return response()->json([
            'data' => [
                'bid' => $this->formatBid($taskBid->fresh()),
                'task' => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'agent_id' => $task->agent_id,
                    'status' => $task->status,
                ],
            ],
        ]);
    }

    /**
     * GET /api/projects/{project}/advertisements
     * Get active capability advertisements.
     */
    public function advertisements(Project $project): JsonResponse
    {
        $advertisements = $this->advertisementService->getActive($project->id);

        return response()->json([
            'data' => $advertisements->map(fn ($ad) => $this->formatAdvertisement($ad)),
        ]);
    }

    /**
     * POST /api/projects/{project}/advertisements/refresh
     * Refresh all agent advertisements.
     */
    public function refreshAdvertisements(Project $project): JsonResponse
    {
        $count = $this->advertisementService->refreshAll($project->id);

        $advertisements = $this->advertisementService->getActive($project->id);

        return response()->json([
            'data' => $advertisements->map(fn ($ad) => $this->formatAdvertisement($ad)),
            'refreshed' => $count,
        ]);
    }

    /**
     * GET /api/projects/{project}/team-formations
     * List team formations.
     */
    public function teamFormations(Project $project): JsonResponse
    {
        $formations = TeamFormation::where('project_id', $project->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (TeamFormation $f) => $this->formatTeamFormation($f));

        return response()->json(['data' => $formations]);
    }

    /**
     * POST /api/projects/{project}/team-formations
     * Form a new team.
     */
    public function formTeam(Project $project, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'objective' => 'required|string|max:2000',
            'strategy' => 'sometimes|in:capability_match,cost_optimized,speed_optimized',
        ]);

        $formation = $this->teamFormationService->formTeam(
            projectId: $project->id,
            objective: $validated['objective'],
            strategy: $validated['strategy'] ?? 'capability_match',
            userId: $request->user()?->id,
        );

        // Override name if provided
        if (! empty($validated['name'])) {
            $formation->update(['name' => $validated['name']]);
        }

        return response()->json([
            'data' => $this->formatTeamFormation($formation->fresh()),
        ], 201);
    }

    /**
     * POST /api/team-formations/{teamFormation}/disband
     * Disband a team.
     */
    public function disbandTeam(TeamFormation $teamFormation): JsonResponse
    {
        if ($teamFormation->status === 'disbanded') {
            return response()->json(['message' => 'Team is already disbanded'], 422);
        }

        $this->teamFormationService->disband($teamFormation);

        return response()->json([
            'data' => $this->formatTeamFormation($teamFormation->fresh()),
        ]);
    }

    /**
     * GET /api/projects/{project}/negotiation-log
     * Paginated negotiation logs.
     */
    public function negotiationLog(Project $project, Request $request): JsonResponse
    {
        $query = NegotiationLog::with('agent:id,name,slug,icon')
            ->orderByDesc('created_at');

        // Filter by project: join through task_bids or team_formations
        // For simplicity, filter by agent_id belonging to agents enabled in this project
        $agentIds = \DB::table('project_agent')
            ->where('project_id', $project->id)
            ->pluck('agent_id');

        $query->whereIn('agent_id', $agentIds);

        if ($request->has('action')) {
            $query->where('action', $request->input('action'));
        }

        $logs = $query->paginate($request->input('per_page', 50));

        return response()->json([
            'data' => collect($logs->items())->map(fn ($log) => $this->formatLog($log)),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    // --- Formatters ---

    private function formatBid(TaskBid $bid): array
    {
        return [
            'id' => $bid->id,
            'uuid' => $bid->uuid,
            'task_id' => $bid->task_id,
            'agent_id' => $bid->agent_id,
            'agent' => $bid->relationLoaded('agent') && $bid->agent ? [
                'id' => $bid->agent->id,
                'name' => $bid->agent->name,
                'slug' => $bid->agent->slug,
                'icon' => $bid->agent->icon,
            ] : null,
            'project_id' => $bid->project_id,
            'bid_score' => (float) $bid->bid_score,
            'estimated_duration_ms' => $bid->estimated_duration_ms,
            'estimated_cost_microcents' => $bid->estimated_cost_microcents,
            'confidence' => (float) $bid->confidence,
            'reasoning' => $bid->reasoning,
            'status' => $bid->status,
            'expires_at' => $bid->expires_at?->toIso8601String(),
            'created_at' => $bid->created_at?->toIso8601String(),
        ];
    }

    private function formatAdvertisement($ad): array
    {
        return [
            'id' => $ad->id,
            'agent_id' => $ad->agent_id,
            'agent' => $ad->relationLoaded('agent') && $ad->agent ? [
                'id' => $ad->agent->id,
                'name' => $ad->agent->name,
                'slug' => $ad->agent->slug,
                'role' => $ad->agent->role,
                'icon' => $ad->agent->icon,
                'description' => $ad->agent->description,
            ] : null,
            'project_id' => $ad->project_id,
            'capabilities' => $ad->capabilities,
            'availability_status' => $ad->availability_status,
            'max_concurrent_tasks' => $ad->max_concurrent_tasks,
            'current_load' => $ad->current_load,
            'advertised_at' => $ad->advertised_at?->toIso8601String(),
            'expires_at' => $ad->expires_at?->toIso8601String(),
        ];
    }

    private function formatTeamFormation(TeamFormation $f): array
    {
        // Resolve agent names
        $agents = [];
        if (! empty($f->agent_ids)) {
            $agents = \App\Models\Agent::whereIn('id', $f->agent_ids)
                ->get(['id', 'name', 'slug', 'icon', 'role'])
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'slug' => $a->slug,
                    'icon' => $a->icon,
                    'role' => $a->role,
                ])
                ->all();
        }

        return [
            'id' => $f->id,
            'uuid' => $f->uuid,
            'project_id' => $f->project_id,
            'name' => $f->name,
            'objective' => $f->objective,
            'formation_strategy' => $f->formation_strategy,
            'agent_ids' => $f->agent_ids,
            'agents' => $agents,
            'status' => $f->status,
            'formed_by_agent_id' => $f->formed_by_agent_id,
            'formed_by_user_id' => $f->formed_by_user_id,
            'performance_score' => $f->performance_score ? (float) $f->performance_score : null,
            'created_at' => $f->created_at?->toIso8601String(),
            'disbanded_at' => $f->disbanded_at?->toIso8601String(),
        ];
    }

    private function formatLog($log): array
    {
        return [
            'id' => $log->id,
            'task_id' => $log->task_id,
            'team_formation_id' => $log->team_formation_id,
            'agent_id' => $log->agent_id,
            'agent' => $log->relationLoaded('agent') && $log->agent ? [
                'id' => $log->agent->id,
                'name' => $log->agent->name,
                'slug' => $log->agent->slug,
                'icon' => $log->agent->icon,
            ] : null,
            'action' => $log->action,
            'details' => $log->details,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }
}
