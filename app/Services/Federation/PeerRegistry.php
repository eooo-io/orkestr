<?php

namespace App\Services\Federation;

use App\Models\FederationPeer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PeerRegistry
{
    /**
     * Register a new federation peer.
     */
    public function register(int $organizationId, string $name, string $baseUrl, string $apiKey): FederationPeer
    {
        $peer = FederationPeer::create([
            'organization_id' => $organizationId,
            'name' => $name,
            'base_url' => rtrim($baseUrl, '/'),
            'api_key_hash' => hash('sha256', $apiKey),
            'status' => 'pending',
            'trust_level' => 'basic',
        ]);

        // Attempt initial handshake
        try {
            $response = Http::timeout(10)
                ->get($peer->base_url . '/api/health');

            if ($response->successful()) {
                $peer->update([
                    'status' => 'active',
                    'last_heartbeat_at' => now(),
                    'metadata' => $response->json(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Federation peer handshake failed', [
                'peer_id' => $peer->id,
                'base_url' => $peer->base_url,
                'error' => $e->getMessage(),
            ]);
        }

        return $peer->refresh();
    }

    /**
     * Perform a heartbeat check on a peer.
     */
    public function heartbeat(FederationPeer $peer): bool
    {
        try {
            $response = Http::timeout(10)
                ->get($peer->base_url . '/api/health');

            if ($response->successful()) {
                $peer->update([
                    'last_heartbeat_at' => now(),
                    'status' => $peer->status === 'pending' ? 'active' : $peer->status,
                ]);

                return true;
            }

            return false;
        } catch (\Throwable $e) {
            Log::warning('Federation peer heartbeat failed', [
                'peer_id' => $peer->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Sync capabilities from a peer.
     */
    public function syncCapabilities(FederationPeer $peer): array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $peer->api_key_hash,
                    'X-Federation-Peer' => $peer->uuid,
                ])
                ->get($peer->base_url . '/api/federation/inbound/capabilities');

            if ($response->successful()) {
                $capabilities = $response->json('data', []);

                $peer->update([
                    'capabilities' => $capabilities,
                    'last_sync_at' => now(),
                ]);

                return $capabilities;
            }

            return $peer->capabilities ?? [];
        } catch (\Throwable $e) {
            Log::warning('Federation peer capability sync failed', [
                'peer_id' => $peer->id,
                'error' => $e->getMessage(),
            ]);

            return $peer->capabilities ?? [];
        }
    }

    /**
     * Update the trust level of a peer.
     */
    public function updateTrust(FederationPeer $peer, string $level): void
    {
        $peer->update(['trust_level' => $level]);
    }

    /**
     * Revoke and remove a peer.
     */
    public function removePeer(FederationPeer $peer): void
    {
        $peer->update(['status' => 'revoked']);
    }
}
