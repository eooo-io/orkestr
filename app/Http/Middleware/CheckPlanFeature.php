<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\Plan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $organization = app()->bound('current_organization')
            ? app('current_organization')
            : null;

        // No org context — allow (backward compat for single-user mode)
        if (! $organization) {
            return $next($request);
        }

        $plan = Plan::findBySlug($organization->plan);

        if (! $plan) {
            return $next($request);
        }

        // Check boolean feature flags
        if (in_array($feature, ['marketplace_publish', 'ai_generation', 'webhook_access', 'bundle_export', 'repository_access', 'priority_support'])) {
            if (! $plan->{$feature}) {
                return response()->json([
                    'error' => 'Feature not available on your current plan.',
                    'feature' => $feature,
                    'current_plan' => $organization->plan,
                    'upgrade_url' => '/settings/billing',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        return $next($request);
    }
}
