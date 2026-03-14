<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * GET /api/audit-logs
     *
     * Paginated list with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AgentAuditLog::query()
            ->with(['agent:id,name,slug', 'user:id,name,email']);

        if ($request->has('event')) {
            $query->forEvent($request->query('event'));
        }

        if ($request->has('agent_id')) {
            $query->forAgent((int) $request->query('agent_id'));
        }

        if ($request->has('user_id')) {
            $query->forUser((int) $request->query('user_id'));
        }

        if ($request->has('from') || $request->has('to')) {
            $query->inDateRange($request->query('from'), $request->query('to'));
        }

        $logs = $query->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 50));

        return response()->json($logs);
    }

    /**
     * GET /api/agents/{agent}/audit-logs
     *
     * Agent-specific audit log.
     */
    public function agentLogs(Request $request, Agent $agent): JsonResponse
    {
        $query = $agent->auditLogs()
            ->with(['user:id,name,email']);

        if ($request->has('event')) {
            $query->forEvent($request->query('event'));
        }

        if ($request->has('from') || $request->has('to')) {
            $query->inDateRange($request->query('from'), $request->query('to'));
        }

        $logs = $query->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 50));

        return response()->json($logs);
    }
}
