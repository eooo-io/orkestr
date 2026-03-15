<?php

namespace App\Http\Controllers;

use App\Models\GuardrailViolation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuardrailReportController extends Controller
{
    /**
     * GET /api/organizations/{org}/guardrail-reports
     * List violations with filters.
     */
    public function index(Request $request, int $org): JsonResponse
    {
        $query = GuardrailViolation::where('organization_id', $org);

        if ($request->has('guard_type')) {
            $query->forGuardType($request->input('guard_type'));
        }
        if ($request->has('severity')) {
            $query->forSeverity($request->input('severity'));
        }
        if ($request->has('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }
        if ($request->has('agent_id')) {
            $query->where('agent_id', $request->input('agent_id'));
        }
        if ($request->boolean('undismissed_only')) {
            $query->undismissed();
        }
        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }
        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->input('to'));
        }

        $violations = $query->orderByDesc('created_at')
            ->paginate($request->input('per_page', 50));

        return response()->json($violations);
    }

    /**
     * GET /api/organizations/{org}/guardrail-reports/trends
     * Violation trends grouped by day and guard_type.
     */
    public function trends(Request $request, int $org): JsonResponse
    {
        $days = (int) $request->input('days', 30);
        $from = now()->subDays($days)->startOfDay();

        $violations = GuardrailViolation::where('organization_id', $org)
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as date, guard_type, severity, COUNT(*) as count')
            ->groupBy('date', 'guard_type', 'severity')
            ->orderBy('date')
            ->get();

        // Summary stats
        $totalViolations = GuardrailViolation::where('organization_id', $org)
            ->where('created_at', '>=', $from)
            ->count();

        $bySeverity = GuardrailViolation::where('organization_id', $org)
            ->where('created_at', '>=', $from)
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity');

        $byGuardType = GuardrailViolation::where('organization_id', $org)
            ->where('created_at', '>=', $from)
            ->selectRaw('guard_type, COUNT(*) as count')
            ->groupBy('guard_type')
            ->pluck('count', 'guard_type');

        $dismissedCount = GuardrailViolation::where('organization_id', $org)
            ->where('created_at', '>=', $from)
            ->whereNotNull('dismissed_at')
            ->count();

        return response()->json([
            'period_days' => $days,
            'total_violations' => $totalViolations,
            'dismissed_count' => $dismissedCount,
            'by_severity' => $bySeverity,
            'by_guard_type' => $byGuardType,
            'daily' => $violations,
        ]);
    }

    /**
     * POST /api/guardrail-violations/{violation}/dismiss
     */
    public function dismiss(Request $request, GuardrailViolation $violation): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $violation->update([
            'dismissed_by' => $request->user()->id,
            'dismissed_at' => now(),
            'dismissal_reason' => $request->input('reason'),
        ]);

        return response()->json(['message' => 'Violation dismissed.']);
    }

    /**
     * GET /api/organizations/{org}/guardrail-reports/export
     */
    public function export(Request $request, int $org): \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
    {
        $format = $request->input('format', 'json');
        $from = $request->input('from', now()->subDays(30)->toDateString());
        $to = $request->input('to', now()->toDateString());

        $violations = GuardrailViolation::where('organization_id', $org)
            ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
            ->orderByDesc('created_at')
            ->limit(10000)
            ->get();

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($violations) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['date', 'guard_type', 'severity', 'rule_name', 'message', 'action_taken', 'dismissed']);

                foreach ($violations as $v) {
                    fputcsv($out, [
                        $v->created_at->toIso8601String(),
                        $v->guard_type,
                        $v->severity,
                        $v->rule_name,
                        $v->message,
                        $v->action_taken,
                        $v->dismissed_at ? 'yes' : 'no',
                    ]);
                }
                fclose($out);
            }, 'guardrail-violations.csv', ['Content-Type' => 'text/csv']);
        }

        return response()->json($violations);
    }
}
