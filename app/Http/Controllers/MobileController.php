<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\PushSubscription;
use App\Services\MobileControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileController extends Controller
{
    public function __construct(
        protected MobileControlService $mobileControl,
    ) {}

    /**
     * POST /api/mobile/push/subscribe
     */
    public function subscribePush(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|url|max:2048',
            'p256dh_key' => 'required|string|max:512',
            'auth_key' => 'required|string|max:512',
        ]);

        $subscription = PushSubscription::updateOrCreate(
            ['endpoint' => $validated['endpoint']],
            [
                'user_id' => $request->user()->id,
                'p256dh_key' => $validated['p256dh_key'],
                'auth_key' => $validated['auth_key'],
                'user_agent' => $request->userAgent(),
            ],
        );

        return response()->json([
            'data' => [
                'id' => $subscription->id,
                'endpoint' => $subscription->endpoint,
                'created_at' => $subscription->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * DELETE /api/mobile/push/unsubscribe
     */
    public function unsubscribePush(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|url|max:2048',
        ]);

        $deleted = PushSubscription::where('endpoint', $validated['endpoint'])
            ->where('user_id', $request->user()->id)
            ->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Subscription not found'], 404);
        }

        return response()->json(['message' => 'Unsubscribed successfully']);
    }

    /**
     * POST /api/mobile/projects/{project}/emergency-kill
     */
    public function emergencyKill(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();

        // Verify user has admin access to the project's organization
        if ($project->organization_id) {
            $orgUser = $user->organizations()
                ->where('organizations.id', $project->organization_id)
                ->first();

            if (! $orgUser || ! in_array($orgUser->pivot->role ?? '', ['owner', 'admin'])) {
                return response()->json(['message' => 'Requires admin or owner role'], 403);
            }
        }

        $result = $this->mobileControl->emergencyKill($project, $user);

        return response()->json(['data' => $result]);
    }

    /**
     * GET /api/mobile/overview
     */
    public function overview(Request $request): JsonResponse
    {
        $data = $this->mobileControl->getMobileOverview($request->user());

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/mobile/pending-approvals
     */
    public function pendingApprovals(Request $request): JsonResponse
    {
        $approvals = $this->mobileControl->getPendingApprovals($request->user());

        return response()->json(['data' => $approvals]);
    }
}
