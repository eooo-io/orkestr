<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrganizationMemberResource;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class OrganizationController extends Controller
{
    // ─── Organization CRUD ────────────────────────────────────────

    /**
     * GET /api/organizations — List user's organizations.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $organizations = $request->user()
            ->organizations()
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return OrganizationResource::collection($organizations);
    }

    /**
     * POST /api/organizations — Create new organization.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $org = Organization::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']) . '-' . Str::random(4),
            'description' => $validated['description'] ?? null,
            'plan' => 'free',
        ]);

        $org->users()->attach($request->user()->id, [
            'role' => 'owner',
            'accepted_at' => now(),
        ]);

        $org->loadCount('users');

        return response()->json([
            'data' => new OrganizationResource($org),
        ], 201);
    }

    /**
     * GET /api/organizations/{organization} — Show org details.
     */
    public function show(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrgMember($request->user(), $organization);

        $organization->loadCount('users');

        return response()->json([
            'data' => new OrganizationResource($organization),
        ]);
    }

    /**
     * PUT /api/organizations/{organization} — Update org.
     */
    public function update(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrgRole($request->user(), $organization, 'admin');

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $organization->update($validated);
        $organization->loadCount('users');

        return response()->json([
            'data' => new OrganizationResource($organization),
        ]);
    }

    /**
     * DELETE /api/organizations/{organization} — Delete org (owner only).
     */
    public function destroy(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrgRole($request->user(), $organization, 'owner');

        // Must not be user's last org
        if ($request->user()->organizations()->count() <= 1) {
            return response()->json([
                'error' => 'Cannot delete your last organization.',
            ], 422);
        }

        // Switch user to another org if this is current
        if ($request->user()->current_organization_id === $organization->id) {
            $otherOrg = $request->user()->organizations()
                ->where('organizations.id', '!=', $organization->id)
                ->first();
            $request->user()->switchOrganization($otherOrg);
        }

        $organization->delete();

        return response()->json(['message' => 'Organization deleted.']);
    }

    /**
     * POST /api/organizations/{organization}/switch — Switch current org.
     */
    public function switch(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrgMember($request->user(), $organization);

        $request->user()->switchOrganization($organization);

        return response()->json([
            'message' => 'Switched to ' . $organization->name,
            'data' => new OrganizationResource($organization->loadCount('users')),
        ]);
    }

    // ─── Members ──────────────────────────────────────────────────

    /**
     * GET /api/organizations/{organization}/members — List members.
     */
    public function members(Request $request, Organization $organization): AnonymousResourceCollection
    {
        $this->authorizeOrgMember($request->user(), $organization);

        $members = $organization->users()
            ->orderByRaw("CASE WHEN organization_user.role = 'owner' THEN 0 WHEN organization_user.role = 'admin' THEN 1 WHEN organization_user.role = 'editor' THEN 2 WHEN organization_user.role = 'member' THEN 3 ELSE 4 END")
            ->get();

        return OrganizationMemberResource::collection($members);
    }

    /**
     * POST /api/organizations/{organization}/members — Invite member by email.
     */
    public function inviteMember(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrgRole($request->user(), $organization, 'admin');

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'role' => 'nullable|string|in:admin,editor,member,viewer',
        ]);

        $email = $validated['email'];
        $role = $validated['role'] ?? 'member';

        // Check if already a member
        if ($organization->users()->where('email', $email)->exists()) {
            return response()->json([
                'error' => 'User is already a member of this organization.',
            ], 422);
        }

        // Check for existing pending invitation
        $existing = OrganizationInvitation::where('organization_id', $organization->id)
            ->where('email', $email)
            ->pending()
            ->first();

        if ($existing) {
            return response()->json([
                'error' => 'An invitation has already been sent to this email.',
            ], 422);
        }

        $invitation = OrganizationInvitation::create([
            'organization_id' => $organization->id,
            'email' => $email,
            'role' => $role,
            'invited_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => [
                'id' => $invitation->id,
                'uuid' => $invitation->uuid,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'token' => $invitation->token,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ],
            'message' => 'Invitation sent.',
        ], 201);
    }

    /**
     * PUT /api/organizations/{organization}/members/{user} — Update member role.
     */
    public function updateMemberRole(Request $request, Organization $organization, User $user): JsonResponse
    {
        $this->authorizeOrgRole($request->user(), $organization, 'admin');

        $validated = $request->validate([
            'role' => 'required|string|in:admin,editor,member,viewer',
        ]);

        // Can't change owner's role
        if ($user->ownsOrganization($organization)) {
            return response()->json([
                'error' => 'Cannot change the owner\'s role.',
            ], 422);
        }

        // Check user is actually a member
        if (! $organization->users()->where('users.id', $user->id)->exists()) {
            return response()->json([
                'error' => 'User is not a member of this organization.',
            ], 404);
        }

        $organization->users()->updateExistingPivot($user->id, [
            'role' => $validated['role'],
        ]);

        return response()->json([
            'message' => 'Member role updated.',
            'data' => [
                'user_id' => $user->id,
                'role' => $validated['role'],
            ],
        ]);
    }

    /**
     * DELETE /api/organizations/{organization}/members/{user} — Remove member.
     */
    public function removeMember(Request $request, Organization $organization, User $user): JsonResponse
    {
        $this->authorizeOrgRole($request->user(), $organization, 'admin');

        // Can't remove the owner
        if ($user->ownsOrganization($organization)) {
            return response()->json([
                'error' => 'Cannot remove the organization owner.',
            ], 422);
        }

        // Check user is actually a member
        if (! $organization->users()->where('users.id', $user->id)->exists()) {
            return response()->json([
                'error' => 'User is not a member of this organization.',
            ], 404);
        }

        $organization->users()->detach($user->id);

        // If removed user had this as current org, switch them
        if ($user->current_organization_id === $organization->id) {
            $otherOrg = $user->organizations()->first();
            if ($otherOrg) {
                $user->switchOrganization($otherOrg);
            } else {
                $user->update(['current_organization_id' => null]);
            }
        }

        return response()->json(['message' => 'Member removed.']);
    }

    // ─── Invitations ──────────────────────────────────────────────

    /**
     * GET /api/organizations/{organization}/invitations — List pending invitations.
     */
    public function invitations(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrgRole($request->user(), $organization, 'admin');

        $invitations = OrganizationInvitation::where('organization_id', $organization->id)
            ->pending()
            ->with('invitedBy')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (OrganizationInvitation $inv) => [
                'id' => $inv->id,
                'uuid' => $inv->uuid,
                'email' => $inv->email,
                'role' => $inv->role,
                'invited_by' => $inv->invitedBy?->name,
                'expires_at' => $inv->expires_at->toIso8601String(),
                'created_at' => $inv->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $invitations]);
    }

    /**
     * DELETE /api/invitations/{invitation} — Cancel invitation.
     */
    public function cancelInvitation(Request $request, OrganizationInvitation $invitation): JsonResponse
    {
        $org = $invitation->organization;
        $this->authorizeOrgRole($request->user(), $org, 'admin');

        $invitation->delete();

        return response()->json(['message' => 'Invitation cancelled.']);
    }

    /**
     * POST /api/invitations/accept/{token} — Accept invitation.
     */
    public function acceptInvitation(Request $request, string $token): JsonResponse
    {
        $invitation = OrganizationInvitation::where('token', $token)->first();

        if (! $invitation) {
            return response()->json(['error' => 'Invalid invitation token.'], 404);
        }

        if (! $invitation->isPending()) {
            return response()->json(['error' => 'Invitation has expired or already been accepted.'], 422);
        }

        $user = $request->user();

        if (strtolower($user->email) !== strtolower($invitation->email)) {
            return response()->json([
                'error' => 'This invitation was sent to a different email address.',
            ], 403);
        }

        // Check if already a member
        if ($invitation->organization->users()->where('users.id', $user->id)->exists()) {
            $invitation->update(['accepted_at' => now()]);

            return response()->json([
                'message' => 'You are already a member of this organization.',
            ]);
        }

        $invitation->accept($user);

        return response()->json([
            'message' => 'Invitation accepted.',
            'data' => new OrganizationResource($invitation->organization->loadCount('users')),
        ]);
    }

    // ─── Authorization Helpers ────────────────────────────────────

    protected function authorizeOrgMember(User $user, Organization $organization): void
    {
        if (! $user->organizations()->where('organizations.id', $organization->id)->exists()) {
            abort(403, 'You are not a member of this organization.');
        }
    }

    protected function authorizeOrgRole(User $user, Organization $organization, string $minimumRole): void
    {
        $this->authorizeOrgMember($user, $organization);

        $hierarchy = ['viewer' => 0, 'member' => 1, 'editor' => 2, 'admin' => 3, 'owner' => 4];

        $userRole = $user->roleInOrganization($organization);
        $userLevel = $hierarchy[$userRole] ?? -1;
        $requiredLevel = $hierarchy[$minimumRole] ?? 99;

        if ($userLevel < $requiredLevel) {
            abort(403, 'Insufficient role. Required: ' . $minimumRole);
        }
    }
}
