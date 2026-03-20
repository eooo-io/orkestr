<?php

namespace App\Services\Federation;

use App\Models\FederatedIdentity;
use App\Models\FederationPeer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FederatedIdentityService
{
    /**
     * Link a local user to a remote identity on a peer.
     */
    public function linkIdentity(
        FederationPeer $peer,
        int $userId,
        string $remoteUserId,
        ?string $remoteEmail = null,
    ): FederatedIdentity {
        return FederatedIdentity::create([
            'user_id' => $userId,
            'peer_id' => $peer->id,
            'remote_user_id' => $remoteUserId,
            'remote_email' => $remoteEmail,
        ]);
    }

    /**
     * Verify a federated identity by checking with the remote peer.
     */
    public function verifyIdentity(FederatedIdentity $identity): bool
    {
        $peer = $identity->peer;

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $peer->api_key_hash,
                    'X-Federation-Peer' => $peer->uuid,
                ])
                ->post($peer->base_url . '/api/federation/inbound/verify-identity', [
                    'remote_user_id' => $identity->remote_user_id,
                ]);

            if ($response->successful() && $response->json('verified', false)) {
                $identity->update([
                    'verified_at' => now(),
                    'remote_role' => $response->json('role'),
                ]);

                return true;
            }

            return false;
        } catch (\Throwable $e) {
            Log::warning('Federation identity verification failed', [
                'identity_id' => $identity->id,
                'peer_id' => $peer->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get all federated identities for a user.
     */
    public function getLinkedIdentities(int $userId): Collection
    {
        return FederatedIdentity::where('user_id', $userId)
            ->with('peer:id,name,base_url,status')
            ->get();
    }

    /**
     * Remove a federated identity link.
     */
    public function unlinkIdentity(FederatedIdentity $identity): void
    {
        $identity->delete();
    }
}
