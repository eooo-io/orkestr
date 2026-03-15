<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSetupComplete
{
    /**
     * Redirect to setup wizard if setup has not been completed.
     *
     * Skips the check for setup, auth, health, and admin routes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();

        // Skip check for setup, auth, health, and admin routes
        $exemptPrefixes = ['api/setup', 'api/auth', 'api/health', 'admin'];

        foreach ($exemptPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $next($request);
            }
        }

        $isComplete = AppSetting::get('setup_completed', false);

        if (! $isComplete) {
            return response()->json([
                'setup_required' => true,
                'message' => 'Please complete the setup wizard.',
            ], 428);
        }

        return $next($request);
    }
}
