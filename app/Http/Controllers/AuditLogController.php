<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        if ($request->has('severity')) {
            $query->forSeverity($request->query('severity'));
        }

        if ($request->has('request_id')) {
            $query->forRequestId($request->query('request_id'));
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

    /**
     * GET /api/audit-logs/export
     *
     * Export audit logs as CSV or JSON download.
     * Accepts: format (csv|json), from, to, event, severity.
     * Limited to 10,000 rows.
     */
    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $format = $request->query('format', 'csv');

        $query = AgentAuditLog::query()
            ->orderByDesc('created_at')
            ->limit(10000);

        if ($request->has('event')) {
            $query->forEvent($request->query('event'));
        }

        if ($request->has('severity')) {
            $query->forSeverity($request->query('severity'));
        }

        if ($request->has('from') || $request->has('to')) {
            $query->inDateRange($request->query('from'), $request->query('to'));
        }

        if ($format === 'json') {
            $logs = $query->get([
                'uuid', 'event', 'severity', 'description',
                'user_email', 'agent_id', 'project_id', 'ip_address', 'created_at',
            ]);

            return response()->json($logs)
                ->withHeaders([
                    'Content-Disposition' => 'attachment; filename="audit-logs.json"',
                    'Content-Type' => 'application/json',
                ]);
        }

        // Default: CSV streamed download
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="audit-logs.csv"',
            'Cache-Control' => 'no-store, no-cache',
            'Pragma' => 'no-cache',
        ];

        $csvColumns = [
            'uuid', 'event', 'severity', 'description',
            'user_email', 'agent_id', 'project_id', 'ip_address', 'created_at',
        ];

        return response()->stream(function () use ($query, $csvColumns) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, $csvColumns);

            $query->chunk(500, function ($rows) use ($handle, $csvColumns) {
                foreach ($rows as $row) {
                    $line = [];
                    foreach ($csvColumns as $col) {
                        $line[] = $row->{$col};
                    }
                    fputcsv($handle, $line);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}
