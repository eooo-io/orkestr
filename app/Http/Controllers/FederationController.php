<?php

namespace App\Http\Controllers;

use App\Models\FederatedIdentity;
use App\Models\FederationDelegation;
use App\Models\FederationPeer;
use App\Services\Federation\FederatedDelegationService;
use App\Services\Federation\FederatedIdentityService;
use App\Services\Federation\PeerRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FederationController extends Controller
{
    public function __construct(
        protected PeerRegistry $registry,
        protected FederatedDelegationService $delegationService,
        protected FederatedIdentityService $identityService,
    ) {}

    /**
     * GET /api/federation/peers
     */
    public function peers(Request $request): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $peers = FederationPeer::where('organization_id', $orgId)
            ->where('status', '!=', 'revoked')
            ->withCount('delegations')
            ->orderBy('name')
            ->get()
            ->map(fn (FederationPeer $p) => $this->formatPeer($p));

        return response()->json(['data' => $peers]);
    }

    /**
     * POST /api/federation/peers
     */
    public function registerPeer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'base_url' => 'required|url|max:2000',
            'api_key' => 'required|string|min:16|max:255',
        ]);

        $orgId = $request->user()->current_organization_id;

        $peer = $this->registry->register(
            $orgId,
            $validated['name'],
            $validated['base_url'],
            $validated['api_key'],
        );

        $peer->loadCount('delegations');

        return response()->json(['data' => $this->formatPeer($peer)], 201);
    }

    /**
     * PUT /api/federation/peers/{federation_peer}
     */
    public function updatePeer(Request $request, FederationPeer $federationPeer): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'base_url' => 'nullable|url|max:2000',
            'trust_level' => 'nullable|string|in:untrusted,basic,verified,full',
            'status' => 'nullable|string|in:pending,active,suspended',
        ]);

        $updates = array_filter($validated, fn ($v) => $v !== null);

        if (isset($updates['trust_level'])) {
            $this->registry->updateTrust($federationPeer, $updates['trust_level']);
            unset($updates['trust_level']);
        }

        if (! empty($updates)) {
            $federationPeer->update($updates);
        }

        $federationPeer->refresh()->loadCount('delegations');

        return response()->json(['data' => $this->formatPeer($federationPeer)]);
    }

    /**
     * DELETE /api/federation/peers/{federation_peer}
     */
    public function removePeer(FederationPeer $federationPeer): JsonResponse
    {
        $this->registry->removePeer($federationPeer);

        return response()->json(['message' => 'Peer revoked']);
    }

    /**
     * POST /api/federation/peers/{federation_peer}/heartbeat
     */
    public function peerHeartbeat(FederationPeer $federationPeer): JsonResponse
    {
        $success = $this->registry->heartbeat($federationPeer);

        $federationPeer->refresh();

        return response()->json([
            'data' => [
                'success' => $success,
                'last_heartbeat_at' => $federationPeer->last_heartbeat_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/federation/peers/{federation_peer}/capabilities
     */
    public function peerCapabilities(FederationPeer $federationPeer): JsonResponse
    {
        $capabilities = $this->registry->syncCapabilities($federationPeer);

        return response()->json(['data' => $capabilities]);
    }

    /**
     * POST /api/federation/peers/{federation_peer}/delegate
     */
    public function delegate(Request $request, FederationPeer $federationPeer): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => 'required|integer|exists:agents,id',
            'remote_agent_slug' => 'required|string|max:255',
            'input' => 'required|array',
        ]);

        $agent = \App\Models\Agent::findOrFail($validated['agent_id']);

        $delegation = $this->delegationService->delegate(
            $federationPeer,
            $agent,
            $validated['remote_agent_slug'],
            $validated['input'],
        );

        return response()->json(['data' => $this->formatDelegation($delegation)], 201);
    }

    /**
     * GET /api/federation/delegations
     */
    public function delegations(Request $request): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $delegations = FederationDelegation::whereHas(
            'peer',
            fn ($q) => $q->where('organization_id', $orgId),
        )
            ->with(['peer:id,name,base_url', 'localAgent:id,name,slug'])
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 50));

        return response()->json([
            'data' => collect($delegations->items())->map(fn ($d) => $this->formatDelegation($d)),
            'meta' => [
                'current_page' => $delegations->currentPage(),
                'last_page' => $delegations->lastPage(),
                'total' => $delegations->total(),
            ],
        ]);
    }

    /**
     * GET /api/federation/delegations/{federation_delegation}
     */
    public function delegationStatus(FederationDelegation $federationDelegation): JsonResponse
    {
        $federationDelegation->load(['peer:id,name,base_url', 'localAgent:id,name,slug']);

        return response()->json(['data' => $this->formatDelegation($federationDelegation)]);
    }

    /**
     * GET /api/federation/identities
     */
    public function identities(Request $request): JsonResponse
    {
        $identities = $this->identityService->getLinkedIdentities($request->user()->id);

        return response()->json([
            'data' => $identities->map(fn (FederatedIdentity $i) => $this->formatIdentity($i)),
        ]);
    }

    /**
     * POST /api/federation/peers/{federation_peer}/link-identity
     */
    public function linkIdentity(Request $request, FederationPeer $federationPeer): JsonResponse
    {
        $validated = $request->validate([
            'remote_user_id' => 'required|string|max:255',
            'remote_email' => 'nullable|email|max:255',
        ]);

        $identity = $this->identityService->linkIdentity(
            $federationPeer,
            $request->user()->id,
            $validated['remote_user_id'],
            $validated['remote_email'] ?? null,
        );

        $identity->load('peer:id,name,base_url,status');

        return response()->json(['data' => $this->formatIdentity($identity)], 201);
    }

    // --- Formatters ---

    private function formatPeer(FederationPeer $peer): array
    {
        return [
            'id' => $peer->id,
            'uuid' => $peer->uuid,
            'organization_id' => $peer->organization_id,
            'name' => $peer->name,
            'base_url' => $peer->base_url,
            'status' => $peer->status,
            'capabilities' => $peer->capabilities,
            'last_heartbeat_at' => $peer->last_heartbeat_at?->toIso8601String(),
            'last_sync_at' => $peer->last_sync_at?->toIso8601String(),
            'trust_level' => $peer->trust_level,
            'metadata' => $peer->metadata,
            'delegations_count' => $peer->delegations_count ?? 0,
            'created_at' => $peer->created_at?->toIso8601String(),
            'updated_at' => $peer->updated_at?->toIso8601String(),
        ];
    }

    private function formatDelegation(FederationDelegation $d): array
    {
        return [
            'id' => $d->id,
            'uuid' => $d->uuid,
            'peer' => $d->peer ? [
                'id' => $d->peer->id,
                'name' => $d->peer->name,
                'base_url' => $d->peer->base_url,
            ] : null,
            'local_agent' => $d->localAgent ? [
                'id' => $d->localAgent->id,
                'name' => $d->localAgent->name,
                'slug' => $d->localAgent->slug,
            ] : null,
            'remote_agent_slug' => $d->remote_agent_slug,
            'direction' => $d->direction,
            'status' => $d->status,
            'input' => $d->input,
            'output' => $d->output,
            'cost_microcents' => $d->cost_microcents,
            'duration_ms' => $d->duration_ms,
            'created_at' => $d->created_at?->toIso8601String(),
            'completed_at' => $d->completed_at?->toIso8601String(),
        ];
    }

    private function formatIdentity(FederatedIdentity $i): array
    {
        return [
            'id' => $i->id,
            'user_id' => $i->user_id,
            'peer' => $i->peer ? [
                'id' => $i->peer->id,
                'name' => $i->peer->name,
                'base_url' => $i->peer->base_url,
                'status' => $i->peer->status,
            ] : null,
            'remote_user_id' => $i->remote_user_id,
            'remote_email' => $i->remote_email,
            'remote_role' => $i->remote_role,
            'verified_at' => $i->verified_at?->toIso8601String(),
            'created_at' => $i->created_at?->toIso8601String(),
        ];
    }
}
