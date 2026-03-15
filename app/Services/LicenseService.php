<?php

namespace App\Services;

use App\Models\LicenseKey;
use App\Models\Organization;
use Illuminate\Support\Str;

class LicenseService
{
    private const TIER_FEATURES = [
        'self_hosted' => [
            'execution',
            'workflows',
            'mcp_servers',
            'a2a_agents',
            'webhooks',
            'repository_sync',
        ],
        'enterprise' => [
            'execution',
            'workflows',
            'mcp_servers',
            'a2a_agents',
            'webhooks',
            'repository_sync',
            'sso',
            'audit_export',
            'custom_branding',
            'priority_support',
            'air_gap_mode',
        ],
    ];

    /**
     * Generate a new license key.
     * Format: ORKESTR-XXXX-XXXX-XXXX-XXXX (uppercase alphanumeric segments)
     */
    public function generate(string $tier, array $options = []): LicenseKey
    {
        $features = self::TIER_FEATURES[$tier] ?? self::TIER_FEATURES['self_hosted'];

        return LicenseKey::create([
            'key' => $this->generateKeyString(),
            'tier' => $tier,
            'status' => 'active',
            'max_users' => $options['max_users'] ?? 0,
            'max_agents' => $options['max_agents'] ?? 0,
            'features' => $features,
            'licensee_name' => $options['licensee_name'] ?? null,
            'licensee_email' => $options['licensee_email'] ?? null,
            'expires_at' => $options['expires_at'] ?? null,
        ]);
    }

    /**
     * Validate and activate a license key for an organization.
     */
    public function activate(string $key, int $organizationId): LicenseKey
    {
        $license = LicenseKey::where('key', $key)->first();

        if (! $license) {
            throw new \InvalidArgumentException('Invalid license key.');
        }

        if ($license->status === 'revoked') {
            throw new \InvalidArgumentException('This license key has been revoked.');
        }

        if ($license->isExpired()) {
            throw new \InvalidArgumentException('This license key has expired.');
        }

        if ($license->organization_id && $license->organization_id !== $organizationId) {
            throw new \InvalidArgumentException('This license key is already activated for another organization.');
        }

        $license->update([
            'organization_id' => $organizationId,
            'activated_at' => $license->activated_at ?? now(),
        ]);

        return $license->fresh();
    }

    /**
     * Check if the current instance has a valid license.
     */
    public function currentLicense(): ?LicenseKey
    {
        $organization = app()->bound('current_organization')
            ? app('current_organization')
            : null;

        if (! $organization) {
            return LicenseKey::active()->latest('activated_at')->first();
        }

        return LicenseKey::active()
            ->where('organization_id', $organization->id)
            ->latest('activated_at')
            ->first();
    }

    /**
     * Validate license constraints (users, agents).
     */
    public function validateConstraints(LicenseKey $license): array
    {
        $violations = [];

        if (! $license->organization_id) {
            return $violations;
        }

        $organization = Organization::find($license->organization_id);

        if (! $organization) {
            return $violations;
        }

        // Check user limit
        if ($license->max_users > 0) {
            $userCount = $organization->users()->count();
            if ($userCount > $license->max_users) {
                $violations[] = [
                    'constraint' => 'max_users',
                    'limit' => $license->max_users,
                    'current' => $userCount,
                    'message' => "Organization has {$userCount} users but license allows {$license->max_users}.",
                ];
            }
        }

        // Check agent limit
        if ($license->max_agents > 0) {
            $agentCount = $organization->projects()
                ->withCount('agents')
                ->get()
                ->sum('agents_count');
            if ($agentCount > $license->max_agents) {
                $violations[] = [
                    'constraint' => 'max_agents',
                    'limit' => $license->max_agents,
                    'current' => $agentCount,
                    'message' => "Organization has {$agentCount} agents but license allows {$license->max_agents}.",
                ];
            }
        }

        // Check expiration
        if ($license->isExpired()) {
            $violations[] = [
                'constraint' => 'expiration',
                'limit' => $license->expires_at->toIso8601String(),
                'current' => now()->toIso8601String(),
                'message' => 'License has expired.',
            ];
        }

        return $violations;
    }

    /**
     * Revoke a license key.
     */
    public function revoke(LicenseKey $license): void
    {
        $license->update(['status' => 'revoked']);
    }

    /**
     * Generate the key string.
     * Format: ORKESTR-XXXX-XXXX-XXXX-XXXX
     */
    private function generateKeyString(): string
    {
        $segments = [];
        for ($i = 0; $i < 4; $i++) {
            $segments[] = strtoupper(Str::random(4));
        }

        $key = 'ORKESTR-' . implode('-', $segments);

        // Ensure uniqueness
        if (LicenseKey::where('key', $key)->exists()) {
            return $this->generateKeyString();
        }

        return $key;
    }
}
