<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserManagementController extends Controller
{
    /**
     * GET /api/users — List users in the current organization (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $org = $this->resolveOrganization($request);

        if (! $org) {
            return response()->json(['data' => [], 'meta' => ['total' => 0]]);
        }

        $this->authorizeOrgRole($request->user(), $org, 'admin');

        $perPage = min((int) $request->query('per_page', 25), 100);

        $users = $org->users()
            ->orderByRaw("CASE WHEN organization_user.role = 'owner' THEN 0 WHEN organization_user.role = 'admin' THEN 1 WHEN organization_user.role = 'editor' THEN 2 WHEN organization_user.role = 'member' THEN 3 ELSE 4 END")
            ->paginate($perPage);

        return response()->json([
            'data' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'role' => $user->pivot->role,
                'accepted_at' => $user->pivot->accepted_at,
                'created_at' => $user->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * POST /api/users — Create a new user and add to current organization.
     */
    public function store(Request $request): JsonResponse
    {
        $org = $this->resolveOrganization($request);

        if (! $org) {
            return response()->json(['error' => 'No organization context.'], 422);
        }

        $this->authorizeOrgRole($request->user(), $org, 'admin');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'string', Password::min(8)],
            'role' => 'nullable|string|in:admin,editor,member,viewer',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $role = $validated['role'] ?? 'member';

        $org->users()->attach($user->id, [
            'role' => $role,
            'accepted_at' => now(),
        ]);

        // Set this as user's current org
        $user->update(['current_organization_id' => $org->id]);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'role' => $role,
                'created_at' => $user->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * PUT /api/users/{user} — Update user details.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $org = $this->resolveOrganization($request);

        if (! $org) {
            return response()->json(['error' => 'No organization context.'], 422);
        }

        $this->authorizeOrgRole($request->user(), $org, 'admin');

        // Verify user is in this org
        if (! $org->users()->where('users.id', $user->id)->exists()) {
            return response()->json(['error' => 'User is not a member of this organization.'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:users,email,' . $user->id,
            'password' => ['sometimes', 'required', 'string', Password::min(8)],
            'role' => 'sometimes|required|string|in:admin,editor,member,viewer',
        ]);

        // Can't change owner's role
        if (isset($validated['role']) && $user->ownsOrganization($org)) {
            return response()->json(['error' => "Cannot change the owner's role."], 422);
        }

        // Update user fields
        $userFields = array_intersect_key($validated, array_flip(['name', 'email', 'password']));
        if (isset($userFields['password'])) {
            $userFields['password'] = Hash::make($userFields['password']);
        }
        if (! empty($userFields)) {
            $user->update($userFields);
        }

        // Update role in pivot
        if (isset($validated['role'])) {
            $org->users()->updateExistingPivot($user->id, [
                'role' => $validated['role'],
            ]);
        }

        $membership = $org->users()->where('users.id', $user->id)->first();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'role' => $membership->pivot->role,
                'created_at' => $user->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * DELETE /api/users/{user} — Remove user from organization (prevent self-delete).
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $org = $this->resolveOrganization($request);

        if (! $org) {
            return response()->json(['error' => 'No organization context.'], 422);
        }

        $this->authorizeOrgRole($request->user(), $org, 'admin');

        // Prevent self-delete
        if ($request->user()->id === $user->id) {
            return response()->json(['error' => 'You cannot remove yourself.'], 422);
        }

        // Can't remove owner
        if ($user->ownsOrganization($org)) {
            return response()->json(['error' => 'Cannot remove the organization owner.'], 422);
        }

        // Verify user is in this org
        if (! $org->users()->where('users.id', $user->id)->exists()) {
            return response()->json(['error' => 'User is not a member of this organization.'], 404);
        }

        $org->users()->detach($user->id);

        // Switch user's current org if needed
        if ($user->current_organization_id === $org->id) {
            $otherOrg = $user->organizations()->first();
            if ($otherOrg) {
                $user->switchOrganization($otherOrg);
            } else {
                $user->update(['current_organization_id' => null]);
            }
        }

        return response()->json(['message' => 'User removed from organization.']);
    }

    // ─── Helpers ────────────────────────────────────────────────

    protected function resolveOrganization(Request $request): ?Organization
    {
        $user = $request->user();

        // Try header first
        $orgId = $request->header('X-Organization-Id') ?: $user->current_organization_id;

        if (! $orgId) {
            return null;
        }

        return Organization::find($orgId);
    }

    protected function authorizeOrgRole(User $user, Organization $organization, string $minimumRole): void
    {
        if (! $user->organizations()->where('organizations.id', $organization->id)->exists()) {
            abort(403, 'You are not a member of this organization.');
        }

        $hierarchy = ['viewer' => 0, 'member' => 1, 'editor' => 2, 'admin' => 3, 'owner' => 4];

        $userRole = $user->roleInOrganization($organization);
        $userLevel = $hierarchy[$userRole] ?? -1;
        $requiredLevel = $hierarchy[$minimumRole] ?? 99;

        if ($userLevel < $requiredLevel) {
            abort(403, 'Insufficient role. Required: ' . $minimumRole);
        }
    }
}
