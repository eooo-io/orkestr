<?php

namespace App\Services\Federation;

use App\Models\Agent;
use App\Models\FederationDelegation;
use App\Models\FederationPeer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FederatedDelegationService
{
    /**
     * Delegate work to a remote agent on a federated peer.
     */
    public function delegate(
        FederationPeer $peer,
        Agent $localAgent,
        string $remoteAgentSlug,
        array $input,
    ): FederationDelegation {
        $delegation = FederationDelegation::create([
            'peer_id' => $peer->id,
            'local_agent_id' => $localAgent->id,
            'remote_agent_slug' => $remoteAgentSlug,
            'direction' => 'outbound',
            'status' => 'pending',
            'input' => $input,
        ]);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $peer->api_key_hash,
                    'X-Federation-Peer' => $peer->uuid,
                ])
                ->post($peer->base_url . '/api/federation/inbound/delegate', [
                    'delegation_uuid' => $delegation->uuid,
                    'remote_agent_slug' => $remoteAgentSlug,
                    'input' => $input,
                    'callback_url' => url('/api/federation/inbound/delegation-callback'),
                ]);

            if ($response->successful()) {
                $delegation->update(['status' => 'active']);
            } else {
                $delegation->update([
                    'status' => 'failed',
                    'output' => ['error' => 'Remote peer returned ' . $response->status()],
                    'completed_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Federation delegation failed', [
                'delegation_id' => $delegation->id,
                'peer_id' => $peer->id,
                'error' => $e->getMessage(),
            ]);

            $delegation->update([
                'status' => 'failed',
                'output' => ['error' => $e->getMessage()],
                'completed_at' => now(),
            ]);
        }

        return $delegation->refresh();
    }

    /**
     * Record the result of a delegation.
     */
    public function receiveResult(
        FederationDelegation $delegation,
        array $output,
        int $costMicrocents,
        int $durationMs,
    ): void {
        $delegation->update([
            'status' => 'completed',
            'output' => $output,
            'cost_microcents' => $costMicrocents,
            'duration_ms' => $durationMs,
            'completed_at' => now(),
        ]);
    }

    /**
     * Get all active delegations for an organization.
     */
    public function getActiveDelegations(int $organizationId): Collection
    {
        return FederationDelegation::whereIn('status', ['pending', 'active'])
            ->whereHas('peer', fn ($q) => $q->where('organization_id', $organizationId))
            ->with(['peer:id,name,base_url', 'localAgent:id,name,slug'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Mark a delegation as failed.
     */
    public function failDelegation(FederationDelegation $delegation, string $reason): void
    {
        $delegation->update([
            'status' => 'failed',
            'output' => ['error' => $reason],
            'completed_at' => now(),
        ]);
    }
}
