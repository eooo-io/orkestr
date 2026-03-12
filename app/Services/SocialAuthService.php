<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SocialAuthService
{
    // ─── GitHub OAuth ─────────────────────────────────────────────

    public function githubRedirectUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => config('services.github.client_id'),
            'redirect_uri' => config('services.github.redirect_uri'),
            'scope' => 'read:user user:email',
            'state' => $state,
        ]);

        return "https://github.com/login/oauth/authorize?{$params}";
    }

    public function handleGitHubCallback(string $code): User
    {
        $tokenResponse = Http::acceptJson()->post('https://github.com/login/oauth/access_token', [
            'client_id' => config('services.github.client_id'),
            'client_secret' => config('services.github.client_secret'),
            'code' => $code,
            'redirect_uri' => config('services.github.redirect_uri'),
        ]);

        $accessToken = $tokenResponse->json('access_token');

        if (! $accessToken) {
            throw new \RuntimeException('GitHub OAuth failed: no access token returned.');
        }

        $userResponse = Http::withToken($accessToken)
            ->acceptJson()
            ->get('https://api.github.com/user');

        if (! $userResponse->successful()) {
            throw new \RuntimeException('Failed to fetch GitHub user profile.');
        }

        $githubUser = $userResponse->json();

        // Fetch primary email if not public
        $email = $githubUser['email'];
        if (! $email) {
            $emailsResponse = Http::withToken($accessToken)
                ->acceptJson()
                ->get('https://api.github.com/user/emails');

            if ($emailsResponse->successful()) {
                $primaryEmail = collect($emailsResponse->json())
                    ->firstWhere('primary', true);
                $email = $primaryEmail['email'] ?? null;
            }
        }

        if (! $email) {
            throw new \RuntimeException('Could not retrieve email from GitHub. Please ensure your GitHub email is accessible.');
        }

        return $this->findOrCreateUser('github', [
            'id' => (string) $githubUser['id'],
            'name' => $githubUser['name'] ?? $githubUser['login'],
            'email' => $email,
            'avatar' => $githubUser['avatar_url'] ?? null,
            'metadata' => [
                'login' => $githubUser['login'],
                'html_url' => $githubUser['html_url'] ?? null,
            ],
        ]);
    }

    // ─── Apple Sign In ────────────────────────────────────────────

    public function appleRedirectUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => config('services.apple.client_id'),
            'redirect_uri' => config('services.apple.redirect_uri'),
            'response_type' => 'code id_token',
            'scope' => 'name email',
            'response_mode' => 'form_post',
            'state' => $state,
        ]);

        return "https://appleid.apple.com/auth/authorize?{$params}";
    }

    public function handleAppleCallback(string $code, ?array $appleUser = null): User
    {
        $tokenResponse = Http::asForm()->post('https://appleid.apple.com/auth/token', [
            'client_id' => config('services.apple.client_id'),
            'client_secret' => $this->generateAppleClientSecret(),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.apple.redirect_uri'),
        ]);

        if (! $tokenResponse->successful()) {
            throw new \RuntimeException('Apple OAuth token exchange failed.');
        }

        $idToken = $tokenResponse->json('id_token');
        $claims = $this->decodeAppleIdToken($idToken);

        $appleId = $claims['sub'] ?? null;
        $email = $claims['email'] ?? null;

        if (! $appleId) {
            throw new \RuntimeException('Invalid Apple ID token.');
        }

        // Apple only sends name on first authorization
        $name = null;
        if ($appleUser && isset($appleUser['name'])) {
            $firstName = $appleUser['name']['firstName'] ?? '';
            $lastName = $appleUser['name']['lastName'] ?? '';
            $name = trim("{$firstName} {$lastName}") ?: null;
        }

        return $this->findOrCreateUser('apple', [
            'id' => $appleId,
            'name' => $name,
            'email' => $email,
            'avatar' => null,
            'metadata' => [
                'email_verified' => $claims['email_verified'] ?? false,
            ],
        ]);
    }

    // ─── Shared User Resolution ───────────────────────────────────

    protected function findOrCreateUser(string $provider, array $data): User
    {
        $providerIdColumn = "{$provider}_id";

        return DB::transaction(function () use ($provider, $providerIdColumn, $data) {
            // 1. Find by provider ID (returning user)
            $user = User::where($providerIdColumn, $data['id'])->first();
            if ($user) {
                $user->update(array_filter([
                    'avatar' => $data['avatar'],
                    'social_metadata' => $data['metadata'],
                ]));

                return $user;
            }

            // 2. Find by email (link social account to existing email user)
            if ($data['email']) {
                $user = User::where('email', $data['email'])->first();
                if ($user) {
                    $user->update([
                        $providerIdColumn => $data['id'],
                        'auth_provider' => $user->auth_provider ?? $provider,
                        'avatar' => $user->avatar ?? $data['avatar'],
                        'social_metadata' => $data['metadata'],
                    ]);

                    return $user;
                }
            }

            // 3. Create new user + personal organization
            $user = User::create([
                'name' => $data['name'] ?? 'User',
                'email' => $data['email'],
                'password' => null,
                $providerIdColumn => $data['id'],
                'auth_provider' => $provider,
                'avatar' => $data['avatar'],
                'email_verified_at' => now(),
                'social_metadata' => $data['metadata'],
            ]);

            $org = Organization::create([
                'name' => $user->name . "'s Workspace",
                'slug' => Str::slug($user->name) . '-' . Str::random(4),
                'plan' => 'free',
            ]);

            $org->users()->attach($user->id, [
                'role' => 'owner',
                'accepted_at' => now(),
            ]);

            $user->update(['current_organization_id' => $org->id]);

            return $user->fresh();
        });
    }

    // ─── Apple JWT Helpers ────────────────────────────────────────

    protected function generateAppleClientSecret(): string
    {
        $teamId = config('services.apple.team_id');
        $clientId = config('services.apple.client_id');
        $keyId = config('services.apple.key_id');
        $privateKey = config('services.apple.private_key');

        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'ES256',
            'kid' => $keyId,
        ]));

        $now = time();
        $payload = $this->base64UrlEncode(json_encode([
            'iss' => $teamId,
            'iat' => $now,
            'exp' => $now + 15777000, // ~6 months
            'aud' => 'https://appleid.apple.com',
            'sub' => $clientId,
        ]));

        $signature = '';
        $key = openssl_pkey_get_private($privateKey);
        openssl_sign("{$header}.{$payload}", $signature, $key, OPENSSL_ALGO_SHA256);

        // Convert DER signature to raw R+S format for ES256
        $signature = $this->derToRaw($signature);

        return "{$header}.{$payload}." . $this->base64UrlEncode($signature);
    }

    protected function decodeAppleIdToken(string $idToken): array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Invalid Apple ID token format.');
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        if (! $payload) {
            throw new \RuntimeException('Failed to decode Apple ID token payload.');
        }

        return $payload;
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected function derToRaw(string $der): string
    {
        $hex = unpack('H*', $der)[1];
        // Skip DER sequence header, extract R and S integers
        $pos = 4;
        $rLen = hexdec(substr($hex, $pos + 2, 2)) * 2;
        $r = substr($hex, $pos + 4, $rLen);
        $pos += 4 + $rLen;
        $sLen = hexdec(substr($hex, $pos + 2, 2)) * 2;
        $s = substr($hex, $pos + 4, $sLen);

        // Pad/trim to 32 bytes each
        $r = str_pad(substr($r, -64), 64, '0', STR_PAD_LEFT);
        $s = str_pad(substr($s, -64), 64, '0', STR_PAD_LEFT);

        return pack('H*', $r . $s);
    }
}
