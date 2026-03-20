<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\FederationDelegation;
use App\Models\FederationPeer;
use App\Services\Federation\FederatedDelegationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FederationInboundController extends Controller
{
    public function __construct(
        protected FederatedDelegationService $delegationService,
    ) {}

    /**
     * POST /api/federation/inbound/heartbeat
     * Public, rate-limited. Validates API key.
     */
    public function receiveHeartbeat(Request $request): JsonResponse
    {
        $peer = $this->authenticatePeer($request);

        if (! $peer) {
            return response()->json(['error' => 'Invalid or unknown peer'], 401);
        }

        $peer->update(['last_heartbeat_at' => now()]);

        return response()->json([
            'data' => [
                'status' => 'ok',
                'name' => config('app.name'),
                'version' => config('app.version', '1.0.0'),
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/federation/inbound/capabilities
     * Public, rate-limited. Validates API key.
     */
    public function capabilities(Request $request): JsonResponse
    {
        $peer = $this->authenticatePeer($request);

        if (! $peer) {
            return response()->json(['error' => 'Invalid or unknown peer'], 401);
        }

        // Return local agents as capabilities
        $agents = Agent::select('id', 'name', 'slug', 'description', 'role')
            ->orderBy('name')
            ->get()
            ->map(fn (Agent $a) => [
                'slug' => $a->slug,
                'name' => $a->name,
                'description' => $a->description,
                'role' => $a->role,
            ])
            ->all();

        $peer->update(['last_sync_at' => now()]);

        return response()->json([
            'data' => [
                'agents' => $agents,
                'version' => config('app.version', '1.0.0'),
                'trust_level' => $peer->trust_level,
            ],
        ]);
    }

    /**
     * POST /api/federation/inbound/delegate
     * Public, rate-limited. Validates API key, creates inbound delegation.
     */
    public function receiveDelegation(Request $request): JsonResponse
    {
        $peer = $this->authenticatePeer($request);

        if (! $peer) {
            return response()->json(['error' => 'Invalid or unknown peer'], 401);
        }

        if ($peer->trust_level === 'untrusted') {
            return response()->json(['error' => 'Peer trust level too low for delegation'], 403);
        }

        $validated = $request->validate([
            'delegation_uuid' => 'required|string|uuid',
            'remote_agent_slug' => 'required|string|max:255',
            'input' => 'required|array',
            'callback_url' => 'nullable|url|max:2000',
        ]);

        // Find the local agent by slug
        $agent = Agent::where('slug', $validated['remote_agent_slug'])->first();

        if (! $agent) {
            return response()->json(['error' => 'Agent not found: ' . $validated['remote_agent_slug']], 404);
        }

        // Create inbound delegation record
        $delegation = FederationDelegation::create([
            'peer_id' => $peer->id,
            'local_agent_id' => $agent->id,
            'remote_agent_slug' => $validated['remote_agent_slug'],
            'direction' => 'inbound',
            'status' => 'active',
            'input' => $validated['input'],
        ]);

        // In a production system, this would dispatch a job to execute the agent.
        // For now, mark as active and the result will be sent via callback.
        // DaemonExecutionJob or similar would pick this up.

        return response()->json([
            'data' => [
                'delegation_uuid' => $delegation->uuid,
                'status' => 'active',
                'agent_slug' => $agent->slug,
                'agent_name' => $agent->name,
            ],
        ], 202);
    }

    /**
     * POST /api/federation/inbound/delegation-callback
     * Public, rate-limited. Receives result from a remote peer.
     */
    public function delegationCallback(Request $request): JsonResponse
    {
        $peer = $this->authenticatePeer($request);

        if (! $peer) {
            return response()->json(['error' => 'Invalid or unknown peer'], 401);
        }

        $validated = $request->validate([
            'delegation_uuid' => 'required|string|uuid',
            'output' => 'required|array',
            'cost_microcents' => 'nullable|integer|min:0',
            'duration_ms' => 'nullable|integer|min:0',
        ]);

        $delegation = FederationDelegation::where('uuid', $validated['delegation_uuid'])
            ->where('peer_id', $peer->id)
            ->whereIn('status', ['pending', 'active'])
            ->first();

        if (! $delegation) {
            return response()->json(['error' => 'Delegation not found or already completed'], 404);
        }

        $this->delegationService->receiveResult(
            $delegation,
            $validated['output'],
            $validated['cost_microcents'] ?? 0,
            $validated['duration_ms'] ?? 0,
        );

        return response()->json([
            'data' => [
                'delegation_uuid' => $delegation->uuid,
                'status' => 'completed',
            ],
        ]);
    }

    /**
     * Authenticate an inbound request by matching the API key hash.
     */
    private function authenticatePeer(Request $request): ?FederationPeer
    {
        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        // The token sent is the plain API key; we hash it to find the peer
        $hash = hash('sha256', $token);

        return FederationPeer::where('api_key_hash', $hash)
            ->whereIn('status', ['pending', 'active'])
            ->first();
    }
}
