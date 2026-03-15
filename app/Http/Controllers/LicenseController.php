<?php

namespace App\Http\Controllers;

use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    public function __construct(
        private LicenseService $licenseService,
    ) {}

    /**
     * GET /api/license/status
     */
    public function status(): JsonResponse
    {
        $license = $this->licenseService->currentLicense();

        if (! $license) {
            return response()->json([
                'licensed' => false,
                'message' => 'No active license found.',
            ]);
        }

        $violations = $this->licenseService->validateConstraints($license);

        return response()->json([
            'licensed' => $license->isActive(),
            'license' => [
                'key' => $license->key,
                'tier' => $license->tier,
                'status' => $license->status,
                'max_users' => $license->max_users,
                'max_agents' => $license->max_agents,
                'features' => $license->features,
                'licensee_name' => $license->licensee_name,
                'licensee_email' => $license->licensee_email,
                'activated_at' => $license->activated_at?->toIso8601String(),
                'expires_at' => $license->expires_at?->toIso8601String(),
                'is_expired' => $license->isExpired(),
                'is_enterprise' => $license->isEnterprise(),
            ],
            'violations' => $violations,
        ]);
    }

    /**
     * POST /api/license/activate
     */
    public function activate(Request $request): JsonResponse
    {
        $request->validate([
            'key' => 'required|string',
        ]);

        $organization = app()->bound('current_organization')
            ? app('current_organization')
            : null;

        if (! $organization) {
            return response()->json([
                'error' => 'No organization context. Cannot activate license.',
            ], 422);
        }

        try {
            $license = $this->licenseService->activate(
                $request->input('key'),
                $organization->id,
            );

            return response()->json([
                'message' => 'License activated successfully.',
                'license' => [
                    'key' => $license->key,
                    'tier' => $license->tier,
                    'status' => $license->status,
                    'features' => $license->features,
                    'activated_at' => $license->activated_at?->toIso8601String(),
                    'expires_at' => $license->expires_at?->toIso8601String(),
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
