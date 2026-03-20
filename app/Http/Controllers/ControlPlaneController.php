<?php

namespace App\Http\Controllers;

use App\Models\ControlPlaneSession;
use App\Services\ControlPlane\ControlPlaneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ControlPlaneController extends Controller
{
    public function __construct(
        protected ControlPlaneService $controlPlaneService,
    ) {}

    /**
     * List sessions for the current user, paginated.
     */
    public function index(Request $request): JsonResponse
    {
        $sessions = ControlPlaneSession::where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->paginate(20);

        return response()->json($sessions);
    }

    /**
     * Create a new session.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'context' => 'nullable|array',
            'context.project_id' => 'nullable|integer|exists:projects,id',
            'context.agent_id' => 'nullable|integer|exists:agents,id',
        ]);

        $session = ControlPlaneSession::create([
            'user_id' => $request->user()->id,
            'organization_id' => $request->user()->current_organization_id,
            'title' => $validated['title'] ?? null,
            'context' => $validated['context'] ?? null,
        ]);

        return response()->json(['data' => $session], 201);
    }

    /**
     * Get session with messages.
     */
    public function show(ControlPlaneSession $session, Request $request): JsonResponse
    {
        if ($session->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => $session->load('messages'),
        ]);
    }

    /**
     * Delete a session.
     */
    public function destroy(ControlPlaneSession $session, Request $request): JsonResponse
    {
        if ($session->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $session->messages()->delete();
        $session->delete();

        return response()->json(['message' => 'Session deleted']);
    }

    /**
     * Send a message and stream the response via SSE.
     */
    public function chat(ControlPlaneSession $session, Request $request): StreamedResponse
    {
        if ($session->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'message' => 'required|string|max:10000',
        ]);

        $user = $request->user();
        $message = $validated['message'];

        return new StreamedResponse(function () use ($session, $message, $user) {
            try {
                $generator = $this->controlPlaneService->chat($session, $message, $user);

                foreach ($generator as $event) {
                    echo 'data: ' . json_encode($event) . "\n\n";
                    if (ob_get_level()) {
                        ob_flush();
                    }
                    flush();
                }
            } catch (\Throwable $e) {
                echo 'data: ' . json_encode(['type' => 'error', 'error' => $e->getMessage()]) . "\n\n";
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * One-shot command without session persistence.
     */
    public function quick(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:10000',
        ]);

        $user = $request->user();
        $message = $validated['message'];

        return new StreamedResponse(function () use ($message, $user) {
            try {
                $generator = $this->controlPlaneService->quick($message, $user);

                foreach ($generator as $event) {
                    echo 'data: ' . json_encode($event) . "\n\n";
                    if (ob_get_level()) {
                        ob_flush();
                    }
                    flush();
                }
            } catch (\Throwable $e) {
                echo 'data: ' . json_encode(['type' => 'error', 'error' => $e->getMessage()]) . "\n\n";
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
