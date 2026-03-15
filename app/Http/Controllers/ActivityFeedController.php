<?php

namespace App\Http\Controllers;

use App\Models\AgentAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityFeedController extends Controller
{
    /**
     * Event-to-description mappings for human-readable activity feed.
     */
    private const EVENT_DESCRIPTIONS = [
        'agent.executed' => ':user ran agent :agent',
        'agent.completed' => 'Agent :agent completed successfully',
        'agent.failed' => 'Agent :agent execution failed',
        'agent.created' => ':user created agent :agent',
        'agent.updated' => ':user updated agent :agent',
        'agent.deleted' => ':user deleted an agent',
        'skill.created' => ':user created skill in :project',
        'skill.updated' => ':user updated a skill in :project',
        'skill.deleted' => ':user deleted a skill',
        'workflow.executed' => ':user ran workflow in :project',
        'workflow.completed' => 'Workflow completed in :project',
        'workflow.failed' => 'Workflow failed in :project',
        'project.created' => ':user created project :project',
        'project.synced' => ':user synced project :project',
        'member.invited' => ':user invited a new team member',
        'member.removed' => ':user removed a team member',
        'member.role_changed' => ':user changed a member role',
        'policy.created' => ':user created a content policy',
        'policy.updated' => ':user updated a content policy',
        'budget.exceeded' => 'Agent :agent exceeded its budget limit',
        'guard.blocked' => 'Guard blocked action for agent :agent',
        'schedule.triggered' => 'Scheduled run triggered for agent :agent',
    ];

    /**
     * Events to exclude from the activity feed (too verbose).
     */
    private const EXCLUDED_EVENTS = [
        'step.started',
        'step.completed',
        'tool.called',
        'token.consumed',
    ];

    /**
     * GET /api/organizations/{organization}/activity-feed
     */
    public function index(Request $request, int $organization): JsonResponse
    {
        $limit = $request->integer('limit', 50);
        $limit = min($limit, 100);

        $query = AgentAuditLog::forOrganization($organization)
            ->with(['user:id,name,email', 'agent:id,name,slug', 'project:id,name'])
            ->whereNotIn('event', self::EXCLUDED_EVENTS)
            ->orderByDesc('created_at');

        if ($request->has('since')) {
            $query->where('created_at', '>=', $request->query('since'));
        }

        $logs = $query->paginate($limit);

        $items = collect($logs->items())->map(function (AgentAuditLog $log) {
            return [
                'id' => $log->id,
                'uuid' => $log->uuid,
                'event' => $log->event,
                'description' => $this->humanize($log),
                'raw_description' => $log->description,
                'severity' => $log->severity ?? 'info',
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                ] : null,
                'agent' => $log->agent ? [
                    'id' => $log->agent->id,
                    'name' => $log->agent->name,
                ] : null,
                'project' => $log->project ? [
                    'id' => $log->project->id,
                    'name' => $log->project->name,
                ] : null,
                'created_at' => $log->created_at->toIso8601String(),
                'time_ago' => $log->created_at->diffForHumans(),
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Convert an audit log entry into a human-readable description.
     */
    private function humanize(AgentAuditLog $log): string
    {
        $template = self::EVENT_DESCRIPTIONS[$log->event] ?? $log->description;

        $userName = $log->user?->name ?? $log->user_email ?? 'Someone';
        $agentName = $log->agent?->name ?? 'an agent';
        $projectName = $log->project?->name ?? 'a project';

        return str_replace(
            [':user', ':agent', ':project'],
            [$userName, $agentName, $projectName],
            $template,
        );
    }
}
