<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\SsoProvider;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SsoService
{
    // ─── SAML ──────────────────────────────────────────────────────

    /**
     * Build the SAML AuthnRequest redirect URL.
     */
    public function samlRedirectUrl(SsoProvider $provider, string $relayState): string
    {
        $id = '_' . Str::random(42);
        $issueInstant = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $callbackUrl = $provider->callbackUrl();
        $entityId = config('app.url');

        $request = <<<XML
        <samlp:AuthnRequest
            xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
            xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
            ID="{$id}"
            Version="2.0"
            IssueInstant="{$issueInstant}"
            Destination="{$provider->sso_url}"
            AssertionConsumerServiceURL="{$callbackUrl}"
            ProtocolBinding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST">
            <saml:Issuer>{$entityId}</saml:Issuer>
            <samlp:NameIDPolicy Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress" AllowCreate="true"/>
        </samlp:AuthnRequest>
        XML;

        $deflated = gzdeflate($request);
        $encoded = base64_encode($deflated);

        $params = http_build_query([
            'SAMLRequest' => $encoded,
            'RelayState' => $relayState,
        ]);

        return "{$provider->sso_url}?{$params}";
    }

    /**
     * Process a SAML Response (ACS callback).
     *
     * @return array{email: string, name: string, attributes: array}
     */
    public function processSamlResponse(SsoProvider $provider, string $samlResponse): array
    {
        $xml = base64_decode($samlResponse);

        // Suppress XML errors for controlled parsing
        $previousUseErrors = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        libxml_use_internal_errors($previousUseErrors);

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('samlp', 'urn:oasis:names:tc:SAML:2.0:protocol');
        $xpath->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');

        // Check status
        $statusCode = $xpath->query('//samlp:StatusCode/@Value');
        if ($statusCode->length === 0) {
            throw new \RuntimeException('Invalid SAML response: missing status code.');
        }
        $status = $statusCode->item(0)->nodeValue;
        if (! str_contains($status, 'Success')) {
            throw new \RuntimeException("SAML authentication failed: {$status}");
        }

        // Extract attributes
        $claimMapping = $provider->getEffectiveClaimMapping();
        $attributes = [];

        $attrNodes = $xpath->query('//saml:Attribute');
        foreach ($attrNodes as $node) {
            $attrName = $node->getAttribute('Name');
            $valueNodes = $xpath->query('saml:AttributeValue', $node);
            $value = $valueNodes->length > 0 ? $valueNodes->item(0)->nodeValue : null;
            $attributes[$attrName] = $value;
        }

        // Extract NameID as fallback for email
        $nameIdNodes = $xpath->query('//saml:NameID');
        $nameId = $nameIdNodes->length > 0 ? $nameIdNodes->item(0)->nodeValue : null;

        $email = $attributes[$claimMapping['email']] ?? $nameId;
        $name = $attributes[$claimMapping['name']]
            ?? trim(($attributes[$claimMapping['first_name']] ?? '') . ' ' . ($attributes[$claimMapping['last_name']] ?? ''))
            ?: null;

        if (! $email) {
            throw new \RuntimeException('SAML response did not contain an email address.');
        }

        return [
            'email' => $email,
            'name' => $name ?: explode('@', $email)[0],
            'attributes' => $attributes,
        ];
    }

    // ─── OIDC ──────────────────────────────────────────────────────

    /**
     * Build the OIDC authorization redirect URL.
     */
    public function oidcRedirectUrl(SsoProvider $provider, string $state): string
    {
        $config = $this->getOidcDiscovery($provider);

        $params = http_build_query([
            'client_id' => $provider->client_id,
            'redirect_uri' => $provider->callbackUrl(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
        ]);

        return $config['authorization_endpoint'] . '?' . $params;
    }

    /**
     * Exchange OIDC authorization code for user info.
     *
     * @return array{email: string, name: string, attributes: array}
     */
    public function processOidcCallback(SsoProvider $provider, string $code): array
    {
        $config = $this->getOidcDiscovery($provider);

        // Exchange code for tokens
        $tokenResponse = Http::asForm()->post($config['token_endpoint'], [
            'client_id' => $provider->client_id,
            'client_secret' => $provider->client_secret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $provider->callbackUrl(),
        ]);

        if (! $tokenResponse->successful()) {
            throw new \RuntimeException('OIDC token exchange failed: ' . $tokenResponse->body());
        }

        $tokens = $tokenResponse->json();
        $accessToken = $tokens['access_token'] ?? null;
        $idToken = $tokens['id_token'] ?? null;

        if (! $accessToken) {
            throw new \RuntimeException('OIDC token response missing access_token.');
        }

        // Decode ID token for claims
        $claims = [];
        if ($idToken) {
            $parts = explode('.', $idToken);
            if (count($parts) === 3) {
                $claims = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true) ?: [];
            }
        }

        // Also fetch userinfo endpoint for complete data
        if (isset($config['userinfo_endpoint'])) {
            $userInfoResponse = Http::withToken($accessToken)
                ->acceptJson()
                ->get($config['userinfo_endpoint']);

            if ($userInfoResponse->successful()) {
                $claims = array_merge($claims, $userInfoResponse->json());
            }
        }

        $claimMapping = $provider->getEffectiveClaimMapping();

        $email = $claims[$claimMapping['email']] ?? null;
        $name = $claims[$claimMapping['name']]
            ?? trim(($claims[$claimMapping['first_name']] ?? '') . ' ' . ($claims[$claimMapping['last_name']] ?? ''))
            ?: null;

        if (! $email) {
            throw new \RuntimeException('OIDC response did not contain an email address.');
        }

        return [
            'email' => $email,
            'name' => $name ?: explode('@', $email)[0],
            'attributes' => $claims,
        ];
    }

    // ─── User Provisioning ─────────────────────────────────────────

    /**
     * Find or create a user from SSO data, attach to the org.
     */
    public function findOrCreateUser(SsoProvider $provider, array $userData): User
    {
        $email = $userData['email'];
        $name = $userData['name'];

        // Validate domain
        if (! $provider->isDomainAllowed($email)) {
            throw new \RuntimeException(
                "Email domain not allowed for this organization's SSO configuration."
            );
        }

        return DB::transaction(function () use ($provider, $email, $name, $userData) {
            // Find existing user by email
            $user = User::where('email', $email)->first();

            if ($user) {
                // Update SSO metadata
                $user->update([
                    'social_metadata' => array_merge(
                        $user->social_metadata ?? [],
                        ['sso_provider' => $provider->uuid, 'sso_attributes' => $userData['attributes']],
                    ),
                ]);

                // Ensure user is member of the org
                $org = $provider->organization;
                if (! $org->users()->where('user_id', $user->id)->exists()) {
                    $org->users()->attach($user->id, [
                        'role' => $provider->default_role,
                        'accepted_at' => now(),
                    ]);
                }

                return $user;
            }

            // Auto-provision new user
            if (! $provider->auto_provision) {
                throw new \RuntimeException(
                    'User not found and auto-provisioning is disabled for this SSO provider.'
                );
            }

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => null,
                'auth_provider' => 'sso',
                'email_verified_at' => now(),
                'social_metadata' => [
                    'sso_provider' => $provider->uuid,
                    'sso_attributes' => $userData['attributes'],
                ],
            ]);

            // Attach to the SSO org
            $org = $provider->organization;
            $org->users()->attach($user->id, [
                'role' => $provider->default_role,
                'accepted_at' => now(),
            ]);

            $user->update(['current_organization_id' => $org->id]);

            return $user->fresh();
        });
    }

    // ─── OIDC Discovery ────────────────────────────────────────────

    /**
     * Fetch and cache the OIDC discovery document.
     */
    private function getOidcDiscovery(SsoProvider $provider): array
    {
        $discoveryUrl = $provider->metadata_url;

        // Append well-known path if not present
        if (! str_contains($discoveryUrl, '.well-known')) {
            $discoveryUrl = rtrim($discoveryUrl, '/') . '/.well-known/openid-configuration';
        }

        $response = Http::acceptJson()->get($discoveryUrl);

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to fetch OIDC discovery document from {$discoveryUrl}");
        }

        $config = $response->json();

        if (! isset($config['authorization_endpoint'], $config['token_endpoint'])) {
            throw new \RuntimeException('Invalid OIDC discovery document: missing required endpoints.');
        }

        return $config;
    }

    // ─── Connection Testing ────────────────────────────────────────

    /**
     * Test connectivity for an SSO provider.
     */
    public function testConnection(SsoProvider $provider): array
    {
        try {
            if ($provider->isSaml()) {
                return $this->testSamlConnection($provider);
            }

            return $this->testOidcConnection($provider);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function testSamlConnection(SsoProvider $provider): array
    {
        if (empty($provider->sso_url)) {
            return ['success' => false, 'error' => 'SSO URL is not configured.'];
        }

        if (empty($provider->certificate)) {
            return ['success' => false, 'error' => 'IdP certificate is not configured.'];
        }

        // Validate certificate format
        if (! str_contains($provider->certificate, 'BEGIN CERTIFICATE')) {
            return ['success' => false, 'error' => 'Certificate does not appear to be in PEM format.'];
        }

        return [
            'success' => true,
            'message' => 'SAML configuration looks valid. SSO URL and certificate are present.',
            'callback_url' => $provider->callbackUrl(),
        ];
    }

    private function testOidcConnection(SsoProvider $provider): array
    {
        if (empty($provider->metadata_url)) {
            return ['success' => false, 'error' => 'Discovery URL is not configured.'];
        }

        $config = $this->getOidcDiscovery($provider);

        return [
            'success' => true,
            'message' => 'OIDC discovery document fetched successfully.',
            'issuer' => $config['issuer'] ?? 'unknown',
            'callback_url' => $provider->callbackUrl(),
        ];
    }
}
