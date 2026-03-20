<?php

namespace App\Services;

use App\Models\AgentProcess;
use App\Models\ApprovalGate;
use App\Models\ExecutionRun;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MobileControlService
{
    public function __construct(
        protected DaemonProcessManager $processManager,
    ) {}

    /**
     * Emergency kill — stop ALL running agent processes for a project.
     */
    public function emergencyKill(Project $project, User $user): array
    {
        $processes = AgentProcess::where('project_id', $project->id)
            ->active()
            ->with('agent:id,name')
            ->get();

        $killed = [];

        foreach ($processes as $process) {
            $this->processManager->kill($process);
            $killed[] = [
                'id' => $process->id,
                'uuid' => $process->uuid,
                'agent_name' => $process->agent?->name ?? 'Unknown',
                'previous_status' => $process->getOriginal('status'),
            ];
        }

        Log::warning('Emergency kill executed', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'project_id' => $project->id,
            'project_name' => $project->name,
            'killed_count' => count($killed),
            'processes' => collect($killed)->pluck('uuid')->all(),
        ]);

        return [
            'killed_count' => count($killed),
            'processes' => $killed,
        ];
    }

    /**
     * Lightweight dashboard overview for mobile.
     */
    public function getMobileOverview(User $user): array
    {
        // Get organization context for project scoping
        $orgId = $user->current_organization_id;

        // Active agent processes
        $activeProcesses = AgentProcess::active()
            ->when($orgId, function ($query) use ($orgId) {
                $query->whereHas('project', fn ($q) => $q->where('organization_id', $orgId));
            })
            ->with('agent:id,name')
            ->get();

        $healthyCount = $activeProcesses->filter(fn ($p) => $p->isHealthy())->count();
        $unhealthyCount = $activeProcesses->count() - $healthyCount;

        // Pending approval gates
        $pendingApprovals = ApprovalGate::pending()
            ->when($orgId, function ($query) use ($orgId) {
                $query->whereHas('project', fn ($q) => $q->where('organization_id', $orgId));
            })
            ->count();

        // Recent execution runs
        $recentRuns = ExecutionRun::query()
            ->when($orgId, function ($query) use ($orgId) {
                $query->whereHas('project', fn ($q) => $q->where('organization_id', $orgId));
            })
            ->with(['agent:id,name', 'project:id,name'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (ExecutionRun $run) => [
                'id' => $run->id,
                'uuid' => $run->uuid,
                'agent' => $run->agent ? ['id' => $run->agent->id, 'name' => $run->agent->name] : null,
                'project' => $run->project ? ['id' => $run->project->id, 'name' => $run->project->name] : null,
                'status' => $run->status,
                'trigger_type' => $run->trigger_type,
                'started_at' => $run->started_at?->toIso8601String(),
                'completed_at' => $run->completed_at?->toIso8601String(),
            ]);

        return [
            'active_agents' => $activeProcesses->count(),
            'pending_approvals' => $pendingApprovals,
            'recent_runs' => $recentRuns,
            'fleet_health' => [
                'healthy' => $healthyCount,
                'unhealthy' => $unhealthyCount,
            ],
        ];
    }

    /**
     * Get all pending approval gates the user can respond to.
     */
    public function getPendingApprovals(User $user): Collection
    {
        $orgId = $user->current_organization_id;

        return ApprovalGate::pending()
            ->when($orgId, function ($query) use ($orgId) {
                $query->whereHas('project', fn ($q) => $q->where('organization_id', $orgId));
            })
            ->with(['agent:id,name', 'project:id,name'])
            ->orderByDesc('requested_at')
            ->get()
            ->map(fn (ApprovalGate $gate) => [
                'id' => $gate->id,
                'uuid' => $gate->uuid,
                'agent' => $gate->agent ? ['id' => $gate->agent->id, 'name' => $gate->agent->name] : null,
                'project' => $gate->project ? ['id' => $gate->project->id, 'name' => $gate->project->name] : null,
                'type' => $gate->type,
                'title' => $gate->title,
                'description' => $gate->description,
                'status' => $gate->status,
                'requested_at' => $gate->requested_at?->toIso8601String(),
                'expires_at' => $gate->expires_at?->toIso8601String(),
            ]);
    }
}
