<?php

namespace App\Http\Controllers;

use App\Models\SsoProvider;
use App\Services\SsoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SsoProviderController extends Controller
{
    public function __construct(
        private SsoService $ssoService,
    ) {}

    /**
     * GET /api/organizations/{organization}/sso-providers
     */
    public function index(int $organization): JsonResponse
    {
        $providers = SsoProvider::forOrganization($organization)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $providers]);
    }

    /**
     * POST /api/organizations/{organization}/sso-providers
     */
    public function store(Request $request, int $organization): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:saml,oidc',
            'name' => 'required|string|max:255',
            'entity_id' => 'nullable|string|max:1000',
            'metadata_url' => 'nullable|url|max:2000',
            'sso_url' => 'nullable|url|max:2000',
            'slo_url' => 'nullable|url|max:2000',
            'certificate' => 'nullable|string',
            'client_id' => 'nullable|string|max:500',
            'client_secret' => 'nullable|string|max:500',
            'claim_mapping' => 'nullable|array',
            'allowed_domains' => 'nullable|array',
            'allowed_domains.*' => 'string',
            'auto_provision' => 'boolean',
            'default_role' => 'in:member,editor,admin',
            'is_active' => 'boolean',
        ]);

        $provider = SsoProvider::create([
            'organization_id' => $organization,
            ...$validated,
        ]);

        return response()->json(['data' => $provider], 201);
    }

    /**
     * GET /api/sso-providers/{ssoProvider}
     */
    public function show(SsoProvider $ssoProvider): JsonResponse
    {
        return response()->json(['data' => $ssoProvider]);
    }

    /**
     * PUT /api/sso-providers/{ssoProvider}
     */
    public function update(Request $request, SsoProvider $ssoProvider): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'entity_id' => 'nullable|string|max:1000',
            'metadata_url' => 'nullable|url|max:2000',
            'sso_url' => 'nullable|url|max:2000',
            'slo_url' => 'nullable|url|max:2000',
            'certificate' => 'nullable|string',
            'client_id' => 'nullable|string|max:500',
            'client_secret' => 'nullable|string|max:500',
            'claim_mapping' => 'nullable|array',
            'allowed_domains' => 'nullable|array',
            'allowed_domains.*' => 'string',
            'auto_provision' => 'boolean',
            'default_role' => 'in:member,editor,admin',
            'is_active' => 'boolean',
        ]);

        $ssoProvider->update($validated);

        return response()->json(['data' => $ssoProvider->fresh()]);
    }

    /**
     * DELETE /api/sso-providers/{ssoProvider}
     */
    public function destroy(SsoProvider $ssoProvider): JsonResponse
    {
        $ssoProvider->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/sso-providers/{ssoProvider}/test
     */
    public function test(SsoProvider $ssoProvider): JsonResponse
    {
        $result = $this->ssoService->testConnection($ssoProvider);

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
