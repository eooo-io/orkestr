<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\SsoProvider;
use App\Models\User;
use App\Services\SocialAuthService;
use App\Services\SsoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(
        private SocialAuthService $socialAuth,
        private SsoService $ssoService,
    ) {}

    // ─── Email Registration ───────────────────────────────────────

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'auth_provider' => 'email',
        ]);

        // Create personal organization
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

        Auth::login($user->fresh());
        $request->session()->regenerate();

        return response()->json([
            'user' => $this->formatUser($user->fresh()),
            'message' => 'Registration successful.',
        ], 201);
    }

    // ─── Email Login ──────────────────────────────────────────────

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($validated, $request->boolean('remember'))) {
            return response()->json([
                'message' => 'Invalid email or password.',
            ], 422);
        }

        $request->session()->regenerate();

        return response()->json([
            'user' => $this->formatUser(Auth::user()),
        ]);
    }

    // ─── Logout ───────────────────────────────────────────────────

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    // ─── Current User ─────────────────────────────────────────────

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['user' => null], 401);
        }

        return response()->json([
            'user' => $this->formatUser($user),
        ]);
    }

    // ─── GitHub OAuth ─────────────────────────────────────────────

    public function githubRedirect(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('oauth_state', $state);

        return redirect($this->socialAuth->githubRedirectUrl($state));
    }

    public function githubCallback(Request $request): RedirectResponse
    {
        $storedState = $request->session()->pull('oauth_state');

        if (! $storedState || $storedState !== $request->query('state')) {
            return redirect($this->spaUrl('/login?error=invalid_state'));
        }

        $code = $request->query('code');
        if (! $code) {
            return redirect($this->spaUrl('/login?error=no_code'));
        }

        try {
            $user = $this->socialAuth->handleGitHubCallback($code);
            Auth::login($user, remember: true);
            $request->session()->regenerate();

            return redirect($this->spaUrl('/projects'));
        } catch (\Exception $e) {
            return redirect($this->spaUrl('/login?error=' . urlencode($e->getMessage())));
        }
    }

    // ─── Apple Sign In ────────────────────────────────────────────

    public function appleRedirect(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('oauth_state', $state);

        return redirect($this->socialAuth->appleRedirectUrl($state));
    }

    public function appleCallback(Request $request): RedirectResponse
    {
        $storedState = $request->session()->pull('oauth_state');

        if (! $storedState || $storedState !== $request->input('state')) {
            return redirect($this->spaUrl('/login?error=invalid_state'));
        }

        $code = $request->input('code');
        if (! $code) {
            return redirect($this->spaUrl('/login?error=no_code'));
        }

        // Apple sends user info as JSON string on first auth
        $appleUser = null;
        if ($request->has('user')) {
            $appleUser = json_decode($request->input('user'), true);
        }

        try {
            $user = $this->socialAuth->handleAppleCallback($code, $appleUser);
            Auth::login($user, remember: true);
            $request->session()->regenerate();

            return redirect($this->spaUrl('/projects'));
        } catch (\Exception $e) {
            return redirect($this->spaUrl('/login?error=' . urlencode($e->getMessage())));
        }
    }

    // ─── SAML SSO ──────────────────────────────────────────────────

    public function samlRedirect(Request $request, string $uuid): RedirectResponse
    {
        $provider = SsoProvider::where('uuid', $uuid)->active()->firstOrFail();

        $relayState = Str::random(40);
        $request->session()->put('sso_relay_state', $relayState);
        $request->session()->put('sso_provider_uuid', $uuid);

        return redirect($this->ssoService->samlRedirectUrl($provider, $relayState));
    }

    public function samlAcs(Request $request, string $uuid): RedirectResponse
    {
        $provider = SsoProvider::where('uuid', $uuid)->firstOrFail();

        $samlResponse = $request->input('SAMLResponse');
        if (! $samlResponse) {
            return redirect($this->spaUrl('/login?error=missing_saml_response'));
        }

        try {
            $userData = $this->ssoService->processSamlResponse($provider, $samlResponse);
            $user = $this->ssoService->findOrCreateUser($provider, $userData);

            $provider->update(['last_used_at' => now()]);

            Auth::login($user, remember: true);
            $request->session()->regenerate();

            return redirect($this->spaUrl('/projects'));
        } catch (\Exception $e) {
            return redirect($this->spaUrl('/login?error=' . urlencode($e->getMessage())));
        }
    }

    // ─── OIDC SSO ──────────────────────────────────────────────────

    public function oidcRedirect(Request $request, string $uuid): RedirectResponse
    {
        $provider = SsoProvider::where('uuid', $uuid)->active()->firstOrFail();

        $state = Str::random(40);
        $request->session()->put('sso_state', $state);
        $request->session()->put('sso_provider_uuid', $uuid);

        return redirect($this->ssoService->oidcRedirectUrl($provider, $state));
    }

    public function oidcCallback(Request $request, string $uuid): RedirectResponse
    {
        $provider = SsoProvider::where('uuid', $uuid)->firstOrFail();

        $storedState = $request->session()->pull('sso_state');
        if (! $storedState || $storedState !== $request->query('state')) {
            return redirect($this->spaUrl('/login?error=invalid_state'));
        }

        $code = $request->query('code');
        if (! $code) {
            return redirect($this->spaUrl('/login?error=no_code'));
        }

        try {
            $userData = $this->ssoService->processOidcCallback($provider, $code);
            $user = $this->ssoService->findOrCreateUser($provider, $userData);

            $provider->update(['last_used_at' => now()]);

            Auth::login($user, remember: true);
            $request->session()->regenerate();

            return redirect($this->spaUrl('/projects'));
        } catch (\Exception $e) {
            return redirect($this->spaUrl('/login?error=' . urlencode($e->getMessage())));
        }
    }

    // ─── SSO Discovery ─────────────────────────────────────────────

    /**
     * GET /api/auth/sso/{organization}
     * Returns active SSO providers for an organization (used on login page).
     */
    public function ssoProviders(int $organization): JsonResponse
    {
        $providers = SsoProvider::forOrganization($organization)
            ->active()
            ->get(['uuid', 'type', 'name']);

        return response()->json([
            'data' => $providers->map(fn (SsoProvider $p) => [
                'uuid' => $p->uuid,
                'type' => $p->type,
                'name' => $p->name,
                'redirect_url' => match ($p->type) {
                    'saml' => url("/auth/saml/{$p->uuid}/redirect"),
                    'oidc' => url("/auth/oidc/{$p->uuid}/redirect"),
                    default => null,
                },
            ]),
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'auth_provider' => $user->auth_provider,
            'has_password' => $user->hasPassword(),
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'current_organization_id' => $user->current_organization_id,
            'created_at' => $user->created_at->toIso8601String(),
        ];
    }

    private function spaUrl(string $path): string
    {
        $base = config('app.spa_url', 'http://localhost:5173');

        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}
