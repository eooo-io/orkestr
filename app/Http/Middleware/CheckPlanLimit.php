<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\Plan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanLimit
{
    public function handle(Request $request, Closure $next, string $limitType): Response
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

        $exceeded = match ($limitType) {
            'projects' => $organization->projects()->count() >= $plan->max_projects,
            'providers' => false, // checked at project level
            'members' => $organization->users()->count() >= $plan->max_members,
            default => false,
        };

        if ($exceeded) {
            return response()->json([
                'error' => "You've reached the {$limitType} limit for your current plan.",
                'limit_type' => $limitType,
                'current_plan' => $organization->plan,
                'upgrade_url' => '/settings/billing',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
