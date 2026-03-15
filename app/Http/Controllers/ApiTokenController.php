<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiTokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tokens = ApiToken::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ApiToken $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'abilities' => $t->abilities,
                'last_used_at' => $t->last_used_at?->toIso8601String(),
                'expires_at' => $t->expires_at?->toIso8601String(),
                'created_at' => $t->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $tokens]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'abilities' => 'nullable|array',
            'abilities.*' => 'string|max:100',
            'expires_in_days' => 'nullable|integer|min:1|max:365',
        ]);

        $expiresAt = isset($validated['expires_in_days'])
            ? now()->addDays($validated['expires_in_days'])
            : null;

        $org = app('current_organization');

        $result = ApiToken::createToken(
            user: $request->user(),
            name: $validated['name'],
            abilities: $validated['abilities'] ?? ['*'],
            org: $org,
            expiresAt: $expiresAt,
        );

        return response()->json([
            'data' => [
                'id' => $result['token']->id,
                'name' => $result['token']->name,
                'plain_token' => $result['plain_token'],
                'abilities' => $result['token']->abilities,
                'expires_at' => $result['token']->expires_at?->toIso8601String(),
            ],
            'message' => 'Store this token securely — it will not be shown again.',
        ], 201);
    }

    public function destroy(ApiToken $apiToken, Request $request): JsonResponse
    {
        if ($apiToken->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $apiToken->delete();

        return response()->json(null, 204);
    }
}
