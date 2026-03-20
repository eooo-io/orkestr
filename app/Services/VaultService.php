<?php

namespace App\Services;

use App\Models\VaultAccessGrant;
use App\Models\VaultAuditEntry;
use App\Models\VaultSecret;
use Illuminate\Support\Str;

class VaultService
{
    /**
     * Create a new encrypted secret with an audit entry.
     */
    public function createSecret(
        int $orgId,
        string $name,
        string $type,
        string $value,
        ?string $description = null,
        ?array $metadata = null,
        ?\DateTimeInterface $expiresAt = null,
        ?int $userId = null,
    ): VaultSecret {
        $secret = VaultSecret::create([
            'organization_id' => $orgId,
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $description,
            'type' => $type,
            'encrypted_value' => '', // placeholder, set via mutator below
            'metadata' => $metadata,
            'expires_at' => $expiresAt,
        ]);

        // Use the mutator to encrypt the value
        $secret->setValueAttribute($value);
        $secret->save();

        VaultAuditEntry::create([
            'secret_id' => $secret->id,
            'action' => 'created',
            'actor_type' => $userId ? 'user' : 'system',
            'actor_id' => $userId,
        ]);

        return $secret;
    }

    /**
     * Rotate a secret's value.
     */
    public function rotateSecret(VaultSecret $secret, string $newValue, ?int $userId = null): void
    {
        $secret->setValueAttribute($newValue);
        $secret->rotated_at = now();
        $secret->save();

        VaultAuditEntry::create([
            'secret_id' => $secret->id,
            'action' => 'rotated',
            'actor_type' => $userId ? 'user' : 'system',
            'actor_id' => $userId,
        ]);
    }

    /**
     * Resolve all secrets an agent has access to (via agent or project grants).
     *
     * @return array<string, string> key=>value pairs of decrypted secrets
     */
    public function resolveForAgent(int $agentId, int $projectId): array
    {
        $secretIds = VaultAccessGrant::active()
            ->where(function ($q) use ($agentId, $projectId) {
                $q->where(function ($q2) use ($agentId) {
                    $q2->where('grantee_type', 'agent')
                        ->where('grantee_id', $agentId);
                })->orWhere(function ($q2) use ($projectId) {
                    $q2->where('grantee_type', 'project')
                        ->where('grantee_id', $projectId);
                });
            })
            ->pluck('secret_id')
            ->unique();

        $secrets = VaultSecret::whereIn('id', $secretIds)->get();

        $result = [];
        foreach ($secrets as $secret) {
            $result[$secret->slug] = $secret->getDecryptedValue();

            VaultAuditEntry::create([
                'secret_id' => $secret->id,
                'action' => 'accessed',
                'actor_type' => 'agent',
                'actor_id' => $agentId,
                'metadata' => ['project_id' => $projectId],
            ]);
        }

        return $result;
    }

    /**
     * Grant access to a secret.
     */
    public function grantAccess(
        VaultSecret $secret,
        string $granteeType,
        int $granteeId,
        ?int $grantedBy = null,
    ): VaultAccessGrant {
        $grant = VaultAccessGrant::create([
            'secret_id' => $secret->id,
            'grantee_type' => $granteeType,
            'grantee_id' => $granteeId,
            'granted_by' => $grantedBy,
            'granted_at' => now(),
        ]);

        VaultAuditEntry::create([
            'secret_id' => $secret->id,
            'action' => 'grant_added',
            'actor_type' => $grantedBy ? 'user' : 'system',
            'actor_id' => $grantedBy,
            'metadata' => [
                'grantee_type' => $granteeType,
                'grantee_id' => $granteeId,
            ],
        ]);

        return $grant;
    }

    /**
     * Revoke an access grant.
     */
    public function revokeAccess(VaultAccessGrant $grant, ?int $userId = null): void
    {
        $grant->update(['revoked_at' => now()]);

        VaultAuditEntry::create([
            'secret_id' => $grant->secret_id,
            'action' => 'grant_revoked',
            'actor_type' => $userId ? 'user' : 'system',
            'actor_id' => $userId,
            'metadata' => [
                'grantee_type' => $grant->grantee_type,
                'grantee_id' => $grant->grantee_id,
            ],
        ]);
    }

    /**
     * Check if a grantee can access a secret.
     */
    public function canAccess(VaultSecret $secret, string $granteeType, int $granteeId): bool
    {
        return VaultAccessGrant::where('secret_id', $secret->id)
            ->where('grantee_type', $granteeType)
            ->where('grantee_id', $granteeId)
            ->active()
            ->exists();
    }
}
