<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOrganizationRole
{
    protected array $hierarchy = [
        'viewer' => 0,
        'member' => 1,
        'editor' => 2,
        'admin' => 3,
        'owner' => 4,
    ];

    public function handle(Request $request, Closure $next, string $minimumRole): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Authentication required.'], 401);
        }

        $organization = app()->bound('current_organization')
            ? app('current_organization')
            : null;

        // No org context — allow (backward compat for single-user mode)
        if (! $organization) {
            return $next($request);
        }

        $userRole = $user->roleInOrganization($organization);

        if (! $userRole) {
            return response()->json([
                'error' => 'You are not a member of this organization.',
            ], Response::HTTP_FORBIDDEN);
        }

        $userLevel = $this->hierarchy[$userRole] ?? -1;
        $requiredLevel = $this->hierarchy[$minimumRole] ?? 99;

        if ($userLevel < $requiredLevel) {
            return response()->json([
                'error' => 'Insufficient permissions. Required role: ' . $minimumRole,
                'current_role' => $userRole,
                'required_role' => $minimumRole,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
