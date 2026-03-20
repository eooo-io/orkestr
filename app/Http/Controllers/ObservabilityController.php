<?php

namespace App\Http\Controllers;

use App\Models\AlertIncident;
use App\Models\AlertRule;
use App\Models\CustomMetric;
use App\Models\DashboardLayout;
use App\Services\Observability\CostForecaster;
use App\Services\Observability\MetricEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ObservabilityController extends Controller
{
    public function __construct(
        private MetricEvaluator $evaluator,
        private CostForecaster $forecaster,
    ) {}

    // ─── Custom Metrics ──────────────────────────────────────────────

    /**
     * GET /api/observability/metrics
     */
    public function metrics(Request $request): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $metrics = CustomMetric::where('organization_id', $orgId)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $metrics]);
    }

    /**
     * POST /api/observability/metrics
     */
    public function storeMetric(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'query_type' => 'required|string|in:count_runs,sum_tokens,avg_cost,avg_duration,error_rate,custom',
            'query_config' => 'nullable|array',
            'query_config.project_id' => 'nullable|integer|exists:projects,id',
            'query_config.agent_id' => 'nullable|integer|exists:agents,id',
            'query_config.model' => 'nullable|string|max:100',
            'unit' => 'nullable|string|max:50',
        ]);

        $metric = CustomMetric::create([
            'organization_id' => $request->user()->current_organization_id,
            'name' => $validated['name'],
            'query_type' => $validated['query_type'],
            'query_config' => $validated['query_config'] ?? null,
            'unit' => $validated['unit'] ?? 'count',
        ]);

        return response()->json(['data' => $metric], 201);
    }

    /**
     * PUT /api/observability/metrics/{customMetric}
     */
    public function updateMetric(CustomMetric $customMetric, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'query_type' => 'sometimes|string|in:count_runs,sum_tokens,avg_cost,avg_duration,error_rate,custom',
            'query_config' => 'nullable|array',
            'unit' => 'nullable|string|max:50',
        ]);

        $customMetric->update($validated);

        return response()->json(['data' => $customMetric->fresh()]);
    }

    /**
     * DELETE /api/observability/metrics/{customMetric}
     */
    public function deleteMetric(CustomMetric $customMetric): JsonResponse
    {
        $customMetric->delete();

        return response()->json(null, 204);
    }

    /**
     * GET /api/observability/metrics/{customMetric}/evaluate
     */
    public function evaluateMetric(CustomMetric $customMetric, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $data = $this->evaluator->evaluate(
            $customMetric,
            $validated['from'] ?? null,
            $validated['to'] ?? null,
        );

        return response()->json(['data' => $data]);
    }

    // ─── Alert Rules ────────────────────────────────────────────────

    /**
     * GET /api/observability/alert-rules
     */
    public function alertRules(Request $request): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $rules = AlertRule::where('organization_id', $orgId)
            ->withCount('incidents')
            ->with('notificationChannel:id,name,type')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $rules]);
    }

    /**
     * POST /api/observability/alert-rules
     */
    public function storeAlertRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'metric_slug' => 'required|string|max:255',
            'condition' => 'required|string|in:gt,lt,gte,lte,eq',
            'threshold' => 'required|numeric',
            'window_minutes' => 'nullable|integer|min:1|max:10080',
            'cooldown_minutes' => 'nullable|integer|min:1|max:10080',
            'notification_channel_id' => 'nullable|integer|exists:notification_channels,id',
            'severity' => 'nullable|string|in:info,warning,critical',
            'enabled' => 'nullable|boolean',
        ]);

        $rule = AlertRule::create([
            'organization_id' => $request->user()->current_organization_id,
            'name' => $validated['name'],
            'metric_slug' => $validated['metric_slug'],
            'condition' => $validated['condition'],
            'threshold' => $validated['threshold'],
            'window_minutes' => $validated['window_minutes'] ?? 60,
            'cooldown_minutes' => $validated['cooldown_minutes'] ?? 30,
            'notification_channel_id' => $validated['notification_channel_id'] ?? null,
            'severity' => $validated['severity'] ?? 'warning',
            'enabled' => $validated['enabled'] ?? true,
        ]);

        return response()->json(['data' => $rule->load('notificationChannel:id,name,type')], 201);
    }

    /**
     * PUT /api/observability/alert-rules/{alertRule}
     */
    public function updateAlertRule(AlertRule $alertRule, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'metric_slug' => 'sometimes|string|max:255',
            'condition' => 'sometimes|string|in:gt,lt,gte,lte,eq',
            'threshold' => 'sometimes|numeric',
            'window_minutes' => 'nullable|integer|min:1|max:10080',
            'cooldown_minutes' => 'nullable|integer|min:1|max:10080',
            'notification_channel_id' => 'nullable|integer|exists:notification_channels,id',
            'severity' => 'nullable|string|in:info,warning,critical',
            'enabled' => 'nullable|boolean',
        ]);

        $alertRule->update($validated);

        return response()->json(['data' => $alertRule->fresh()->load('notificationChannel:id,name,type')]);
    }

    /**
     * DELETE /api/observability/alert-rules/{alertRule}
     */
    public function deleteAlertRule(AlertRule $alertRule): JsonResponse
    {
        $alertRule->delete();

        return response()->json(null, 204);
    }

    // ─── Alert Incidents ────────────────────────────────────────────

    /**
     * GET /api/observability/incidents
     */
    public function incidents(Request $request): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $validated = $request->validate([
            'status' => 'nullable|string|in:firing,acknowledged,resolved',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = AlertIncident::query()
            ->whereHas('alertRule', fn ($q) => $q->where('organization_id', $orgId))
            ->with(['alertRule:id,name,severity,metric_slug,condition,threshold', 'acknowledgedBy:id,name'])
            ->orderByDesc('created_at');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $incidents = $query->paginate($validated['per_page'] ?? 25);

        return response()->json($incidents);
    }

    /**
     * POST /api/observability/incidents/{alertIncident}/acknowledge
     */
    public function acknowledgeIncident(AlertIncident $alertIncident, Request $request): JsonResponse
    {
        if (! $alertIncident->isFiring()) {
            return response()->json(['message' => 'Incident is not in firing state.'], 422);
        }

        $alertIncident->update([
            'status' => 'acknowledged',
            'acknowledged_by' => $request->user()->id,
            'acknowledged_at' => now(),
        ]);

        return response()->json(['data' => $alertIncident->fresh()->load('acknowledgedBy:id,name')]);
    }

    /**
     * POST /api/observability/incidents/{alertIncident}/resolve
     */
    public function resolveIncident(AlertIncident $alertIncident): JsonResponse
    {
        if ($alertIncident->isResolved()) {
            return response()->json(['message' => 'Incident is already resolved.'], 422);
        }

        $alertIncident->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        return response()->json(['data' => $alertIncident->fresh()]);
    }

    // ─── Dashboard Layouts ──────────────────────────────────────────

    /**
     * GET /api/observability/dashboards
     */
    public function dashboards(Request $request): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $dashboards = DashboardLayout::where('organization_id', $orgId)
            ->where(function ($q) use ($request) {
                $q->whereNull('user_id')
                    ->orWhere('user_id', $request->user()->id);
            })
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $dashboards]);
    }

    /**
     * POST /api/observability/dashboards
     */
    public function storeDashboard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'layout' => 'required|array',
            'layout.*.metric_slug' => 'required|string',
            'layout.*.chart_type' => 'required|string|in:number,sparkline,bar',
            'is_default' => 'nullable|boolean',
            'shared' => 'nullable|boolean',
        ]);

        // If setting as default, unset other defaults
        if (! empty($validated['is_default'])) {
            DashboardLayout::where('organization_id', $request->user()->current_organization_id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $dashboard = DashboardLayout::create([
            'organization_id' => $request->user()->current_organization_id,
            'user_id' => (! empty($validated['shared'])) ? null : $request->user()->id,
            'name' => $validated['name'],
            'layout' => $validated['layout'],
            'is_default' => $validated['is_default'] ?? false,
        ]);

        return response()->json(['data' => $dashboard], 201);
    }

    /**
     * PUT /api/observability/dashboards/{dashboardLayout}
     */
    public function updateDashboard(DashboardLayout $dashboardLayout, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'layout' => 'sometimes|array',
            'layout.*.metric_slug' => 'required_with:layout|string',
            'layout.*.chart_type' => 'required_with:layout|string|in:number,sparkline,bar',
            'is_default' => 'nullable|boolean',
        ]);

        if (! empty($validated['is_default'])) {
            DashboardLayout::where('organization_id', $dashboardLayout->organization_id)
                ->where('id', '!=', $dashboardLayout->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $dashboardLayout->update($validated);

        return response()->json(['data' => $dashboardLayout->fresh()]);
    }

    /**
     * DELETE /api/observability/dashboards/{dashboardLayout}
     */
    public function deleteDashboard(DashboardLayout $dashboardLayout): JsonResponse
    {
        $dashboardLayout->delete();

        return response()->json(null, 204);
    }

    // ─── Cost Forecast ──────────────────────────────────────────────

    /**
     * GET /api/observability/cost-forecast
     */
    public function costForecast(Request $request): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $data = $this->forecaster->forecast($orgId);

        return response()->json(['data' => $data]);
    }
}
