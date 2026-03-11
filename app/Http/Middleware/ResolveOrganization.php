<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $this->resolveOrganization($request);

        if ($organization) {
            app()->instance('current_organization', $organization);
        }

        return $next($request);
    }

    protected function resolveOrganization(Request $request): ?Organization
    {
        // Priority 1: Explicit header
        if ($orgId = $request->header('X-Organization-Id')) {
            return Organization::where('uuid', $orgId)->first();
        }

        // Priority 2: Authenticated user's current organization
        if ($request->user() && $request->user()->current_organization_id) {
            return Organization::find($request->user()->current_organization_id);
        }

        // Priority 3: First organization for the authenticated user
        if ($request->user()) {
            return $request->user()->organizations()->first();
        }

        return null;
    }
}
