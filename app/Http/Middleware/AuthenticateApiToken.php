<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates requests using Bearer token (API token) as a fallback
 * when session auth is not present. Enables programmatic API access.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // If already authenticated via session, continue
        if ($request->user()) {
            return $next($request);
        }

        $bearer = $request->bearerToken();
        if (! $bearer) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $apiToken = ApiToken::findByPlainToken($bearer);

        if (! $apiToken) {
            return response()->json(['error' => 'Invalid API token.'], 401);
        }

        if ($apiToken->isExpired()) {
            return response()->json(['error' => 'API token expired.'], 401);
        }

        // Set the authenticated user
        auth()->setUser($apiToken->user);
        $apiToken->markUsed();

        // Store token on request for ability checks
        $request->attributes->set('api_token', $apiToken);

        // Resolve organization from token
        if ($apiToken->organization_id) {
            $org = $apiToken->organization;
            if ($org) {
                app()->instance('current_organization', $org);
            }
        }

        return $next($request);
    }
}
