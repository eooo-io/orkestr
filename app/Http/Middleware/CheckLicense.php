<?php

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLicense
{
    public function __construct(
        private LicenseService $licenseService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $license = $this->licenseService->currentLicense();

        if (! $license || ! $license->isActive()) {
            return response()->json([
                'error' => 'A valid license is required to use this feature.',
                'message' => 'Please activate a license key for your organization.',
                'activate_url' => '/settings/license',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
