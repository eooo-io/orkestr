<?php

namespace App\Http\Controllers;

use App\Models\CollaborationComment;
use App\Models\DebugSession;
use App\Models\Project;
use App\Services\Collaboration\ConflictResolver;
use App\Services\Collaboration\PresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollaborationController extends Controller
{
    public function __construct(
        protected PresenceService $presenceService,
        protected ConflictResolver $conflictResolver,
    ) {}

    /**
     * POST /api/collaboration/heartbeat
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'resource_type' => 'required|string|in:skill,agent,workflow',
            'resource_id' => 'required|integer',
            'cursor_position' => 'nullable|array',
            'cursor_position.line' => 'nullable|integer',
            'cursor_position.column' => 'nullable|integer',
            'selection' => 'nullable|array',
        ]);

        $session = $this->presenceService->heartbeat(
            $request->user()->id,
            $validated['resource_type'],
            $validated['resource_id'],
            $validated['cursor_position'] ?? null,
            $validated['selection'] ?? null,
        );

        // Check for edit conflicts on skills
        $conflict = null;
        if ($validated['resource_type'] === 'skill') {
            $conflict = $this->conflictResolver->checkConflict(
                $validated['resource_id'],
                $request->user()->id,
            );
        }

        return response()->json([
            'data' => [
                'session_id' => $session->uuid,
                'color' => $session->color,
                'conflict' => $conflict,
            ],
        ]);
    }

    /**
     * POST /api/collaboration/leave
     */
    public function leave(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'resource_type' => 'required|string|in:skill,agent,workflow',
            'resource_id' => 'required|integer',
        ]);

        $this->presenceService->leave(
            $request->user()->id,
            $validated['resource_type'],
            $validated['resource_id'],
        );

        return response()->json(['message' => 'Left']);
    }

    /**
     * GET /api/collaboration/presence/{type}/{id}
     */
    public function presence(string $type, int $id): JsonResponse
    {
        $users = $this->presenceService->getPresence($type, $id);

        return response()->json(['data' => $users->values()]);
    }

    /**
     * GET /api/collaboration/comments/{type}/{id}
     */
    public function comments(Request $request, string $type, int $id): JsonResponse
    {
        $query = CollaborationComment::forResource($type, $id)
            ->whereNull('thread_id') // Only top-level comments
            ->with(['user:id,name,email', 'resolvedBy:id,name', 'replies.user:id,name,email'])
            ->latest();

        if ($request->query('resolved') === 'false') {
            $query->unresolved();
        }

        $comments = $query->paginate($request->query('per_page', 25));

        return response()->json($comments);
    }

    /**
     * POST /api/collaboration/comments/{type}/{id}
     */
    public function storeComment(Request $request, string $type, int $id): JsonResponse
    {
        $validated = $request->validate([
            'body' => 'required|string|max:5000',
            'line_number' => 'nullable|integer|min:1',
            'thread_id' => 'nullable|integer|exists:collaboration_comments,id',
        ]);

        $comment = CollaborationComment::create([
            'user_id' => $request->user()->id,
            'organization_id' => $request->user()->current_organization_id,
            'resource_type' => $type,
            'resource_id' => $id,
            'thread_id' => $validated['thread_id'] ?? null,
            'line_number' => $validated['line_number'] ?? null,
            'body' => $validated['body'],
        ]);

        $comment->load(['user:id,name,email']);

        return response()->json(['data' => $comment], 201);
    }

    /**
     * POST /api/collaboration/comments/{comment}/resolve
     */
    public function resolveComment(Request $request, CollaborationComment $comment): JsonResponse
    {
        $comment->update([
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
        ]);

        $comment->load(['user:id,name,email', 'resolvedBy:id,name']);

        return response()->json(['data' => $comment]);
    }

    /**
     * DELETE /api/collaboration/comments/{comment}
     */
    public function deleteComment(Request $request, CollaborationComment $comment): JsonResponse
    {
        $user = $request->user();

        // Only the author or an org admin/owner can delete
        if ($comment->user_id !== $user->id) {
            $org = $user->currentOrganization;
            $role = $org?->users()->where('user_id', $user->id)->first()?->pivot?->role;

            if (! in_array($role, ['admin', 'owner'])) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        // Delete replies first
        $comment->replies()->delete();
        $comment->delete();

        return response()->json(['message' => 'Comment deleted']);
    }

    /**
     * GET /api/collaboration/conflicts/{skillId}
     */
    public function checkConflict(Request $request, int $skillId): JsonResponse
    {
        $conflict = $this->conflictResolver->checkConflict($skillId, $request->user()->id);

        return response()->json(['data' => $conflict]);
    }

    /**
     * POST /api/collaboration/conflicts/{skillId}/resolve
     */
    public function resolveConflict(Request $request, int $skillId): JsonResponse
    {
        $validated = $request->validate([
            'strategy' => 'sometimes|string|in:last_write_wins,revert_to_version',
        ]);

        $result = $this->conflictResolver->resolveConflict(
            $skillId,
            $validated['strategy'] ?? 'last_write_wins',
        );

        return response()->json(['data' => $result]);
    }

    /**
     * GET /api/projects/{project}/debug-sessions
     */
    public function debugSessions(Project $project): JsonResponse
    {
        $sessions = $project->debugSessions()
            ->active()
            ->with('creator:id,name,email')
            ->latest()
            ->get();

        return response()->json(['data' => $sessions]);
    }

    /**
     * POST /api/projects/{project}/debug-sessions
     */
    public function createDebugSession(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'execution_run_id' => 'nullable|integer',
        ]);

        $session = $project->debugSessions()->create([
            'created_by' => $request->user()->id,
            'title' => $validated['title'],
            'execution_run_id' => $validated['execution_run_id'] ?? null,
            'participants' => [$request->user()->id],
            'status' => 'active',
        ]);

        $session->load('creator:id,name,email');

        return response()->json(['data' => $session], 201);
    }

    /**
     * POST /api/debug-sessions/{debugSession}/join
     */
    public function joinDebugSession(Request $request, DebugSession $debugSession): JsonResponse
    {
        $participants = $debugSession->participants ?? [];
        $userId = $request->user()->id;

        if (! in_array($userId, $participants)) {
            $participants[] = $userId;
            $debugSession->update(['participants' => $participants]);
        }

        return response()->json(['data' => $debugSession->fresh('creator:id,name,email')]);
    }

    /**
     * POST /api/debug-sessions/{debugSession}/end
     */
    public function endDebugSession(DebugSession $debugSession): JsonResponse
    {
        $debugSession->update([
            'status' => 'ended',
            'ended_at' => now(),
        ]);

        return response()->json(['data' => $debugSession]);
    }
}
