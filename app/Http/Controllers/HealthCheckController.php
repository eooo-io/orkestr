<?php

namespace App\Http\Controllers;

use App\Services\HealthCheckService;
use Illuminate\Http\JsonResponse;

class HealthCheckController extends Controller
{
    public function __construct(
        private HealthCheckService $healthCheckService,
    ) {}

    /**
     * GET /api/diagnostics — run all health checks.
     */
    public function index(): JsonResponse
    {
        $results = $this->healthCheckService->runAll();
        $systemInfo = $this->healthCheckService->systemInfo();

        $overallStatus = collect($results)->contains(fn ($r) => $r['status'] === 'unhealthy')
            ? 'unhealthy'
            : (collect($results)->contains(fn ($r) => $r['status'] === 'degraded') ? 'degraded' : 'healthy');

        return response()->json([
            'status' => $overallStatus,
            'checks' => $results,
            'system' => $systemInfo,
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/diagnostics/{check} — run a single check.
     */
    public function show(string $check): JsonResponse
    {
        $validChecks = ['database', 'cache', 'queue', 'storage', 'anthropic', 'openai', 'ollama'];

        if (! in_array($check, $validChecks)) {
            return response()->json([
                'error' => 'Invalid check. Valid checks: '.implode(', ', $validChecks),
            ], 422);
        }

        $methodMap = [
            'database' => 'checkDatabase',
            'cache' => 'checkCache',
            'queue' => 'checkQueue',
            'storage' => 'checkStorage',
            'anthropic' => fn () => $this->healthCheckService->checkProvider('anthropic'),
            'openai' => fn () => $this->healthCheckService->checkProvider('openai'),
            'ollama' => 'checkOllama',
        ];

        $method = $methodMap[$check];

        $result = is_callable($method)
            ? $method()
            : $this->healthCheckService->{$method}();

        return response()->json([
            'check' => $check,
            ...$result,
            'checked_at' => now()->toIso8601String(),
        ]);
    }
}
