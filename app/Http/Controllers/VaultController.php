<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\VaultAccessGrant;
use App\Models\VaultAuditEntry;
use App\Models\VaultSecret;
use App\Services\VaultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VaultController extends Controller
{
    public function __construct(
        protected VaultService $vault,
    ) {}

    /**
     * GET /api/organizations/{organization}/vault
     */
    public function index(Organization $organization): JsonResponse
    {
        $secrets = VaultSecret::where('organization_id', $organization->id)
            ->withCount('accessGrants')
            ->orderBy('name')
            ->get()
            ->map(fn (VaultSecret $s) => $this->formatSecret($s));

        return response()->json(['data' => $secrets]);
    }

    /**
     * POST /api/organizations/{organization}/vault
     */
    public function store(Request $request, Organization $organization): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:' . implode(',', VaultSecret::validTypes()),
            'value' => 'required|string',
            'description' => 'nullable|string|max:1000',
            'metadata' => 'nullable|array',
            'expires_at' => 'nullable|date',
        ]);

        $secret = $this->vault->createSecret(
            orgId: $organization->id,
            name: $validated['name'],
            type: $validated['type'],
            value: $validated['value'],
            description: $validated['description'] ?? null,
            metadata: $validated['metadata'] ?? null,
            expiresAt: isset($validated['expires_at']) ? new \DateTime($validated['expires_at']) : null,
            userId: $request->user()?->id,
        );

        $secret->loadCount('accessGrants');

        return response()->json(['data' => $this->formatSecret($secret)], 201);
    }

    /**
     * GET /api/vault/{vault_secret}
     */
    public function show(VaultSecret $vaultSecret): JsonResponse
    {
        $vaultSecret->loadCount('accessGrants');

        return response()->json(['data' => $this->formatSecret($vaultSecret)]);
    }

    /**
     * PUT /api/vault/{vault_secret}
     */
    public function update(Request $request, VaultSecret $vaultSecret): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'metadata' => 'nullable|array',
            'expires_at' => 'nullable|date',
        ]);

        $vaultSecret->update(array_filter($validated, fn ($v) => $v !== null));

        VaultAuditEntry::create([
            'secret_id' => $vaultSecret->id,
            'action' => 'updated',
            'actor_type' => $request->user() ? 'user' : 'system',
            'actor_id' => $request->user()?->id,
        ]);

        $vaultSecret->loadCount('accessGrants');

        return response()->json(['data' => $this->formatSecret($vaultSecret)]);
    }

    /**
     * POST /api/vault/{vault_secret}/rotate
     */
    public function rotate(Request $request, VaultSecret $vaultSecret): JsonResponse
    {
        $validated = $request->validate([
            'value' => 'required|string',
        ]);

        $this->vault->rotateSecret(
            $vaultSecret,
            $validated['value'],
            $request->user()?->id,
        );

        $vaultSecret->refresh()->loadCount('accessGrants');

        return response()->json(['data' => $this->formatSecret($vaultSecret)]);
    }

    /**
     * DELETE /api/vault/{vault_secret}
     */
    public function destroy(Request $request, VaultSecret $vaultSecret): JsonResponse
    {
        VaultAuditEntry::create([
            'secret_id' => $vaultSecret->id,
            'action' => 'deleted',
            'actor_type' => $request->user() ? 'user' : 'system',
            'actor_id' => $request->user()?->id,
        ]);

        $vaultSecret->delete();

        return response()->json(null, 204);
    }

    /**
     * GET /api/vault/{vault_secret}/grants
     */
    public function grants(VaultSecret $vaultSecret): JsonResponse
    {
        $grants = $vaultSecret->accessGrants()
            ->with('grantedByUser:id,name,email')
            ->orderByDesc('granted_at')
            ->get()
            ->map(fn (VaultAccessGrant $g) => [
                'id' => $g->id,
                'grantee_type' => $g->grantee_type,
                'grantee_id' => $g->grantee_id,
                'granted_by' => $g->grantedByUser ? [
                    'id' => $g->grantedByUser->id,
                    'name' => $g->grantedByUser->name,
                ] : null,
                'granted_at' => $g->granted_at?->toISOString(),
                'revoked_at' => $g->revoked_at?->toISOString(),
                'is_active' => $g->revoked_at === null,
            ]);

        return response()->json(['data' => $grants]);
    }

    /**
     * POST /api/vault/{vault_secret}/grants
     */
    public function addGrant(Request $request, VaultSecret $vaultSecret): JsonResponse
    {
        $validated = $request->validate([
            'grantee_type' => 'required|string|in:agent,project,user',
            'grantee_id' => 'required|integer',
        ]);

        $grant = $this->vault->grantAccess(
            $vaultSecret,
            $validated['grantee_type'],
            $validated['grantee_id'],
            $request->user()?->id,
        );

        return response()->json([
            'data' => [
                'id' => $grant->id,
                'grantee_type' => $grant->grantee_type,
                'grantee_id' => $grant->grantee_id,
                'granted_at' => $grant->granted_at?->toISOString(),
            ],
        ], 201);
    }

    /**
     * DELETE /api/vault-grants/{vault_access_grant}
     */
    public function revokeGrant(Request $request, VaultAccessGrant $vaultAccessGrant): JsonResponse
    {
        $this->vault->revokeAccess($vaultAccessGrant, $request->user()?->id);

        return response()->json(null, 204);
    }

    /**
     * GET /api/vault/{vault_secret}/audit
     */
    public function audit(VaultSecret $vaultSecret): JsonResponse
    {
        $entries = $vaultSecret->auditEntries()
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (VaultAuditEntry $e) => [
                'id' => $e->id,
                'action' => $e->action,
                'actor_type' => $e->actor_type,
                'actor_id' => $e->actor_id,
                'metadata' => $e->metadata,
                'created_at' => $e->created_at?->toISOString(),
            ]);

        return response()->json(['data' => $entries]);
    }

    /**
     * Format a secret for API response — NEVER expose encrypted_value.
     */
    protected function formatSecret(VaultSecret $secret): array
    {
        return [
            'id' => $secret->id,
            'organization_id' => $secret->organization_id,
            'name' => $secret->name,
            'slug' => $secret->slug,
            'description' => $secret->description,
            'type' => $secret->type,
            'metadata' => $secret->metadata,
            'rotated_at' => $secret->rotated_at?->toISOString(),
            'expires_at' => $secret->expires_at?->toISOString(),
            'access_grants_count' => $secret->access_grants_count ?? 0,
            'created_at' => $secret->created_at?->toISOString(),
            'updated_at' => $secret->updated_at?->toISOString(),
        ];
    }
}
