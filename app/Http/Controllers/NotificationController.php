<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    /**
     * GET /api/notifications — list current user's notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Notification::where('user_id', $request->user()->id)
            ->orderByDesc('created_at');

        if ($request->boolean('unread_only')) {
            $query->unread();
        }

        $notifications = $query->paginate($request->integer('per_page', 50));

        return response()->json([
            'notifications' => $notifications->items(),
            'unread_count' => $this->notificationService->unreadCount($request->user()->id),
            'total' => $notifications->total(),
        ]);
    }

    /**
     * POST /api/notifications/read-all
     */
    public function readAll(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllRead($request->user()->id);

        return response()->json(['marked_read' => $count]);
    }

    /**
     * POST /api/notifications/{id}/read
     */
    public function read(int $id, Request $request): JsonResponse
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $this->notificationService->markRead($notification->id);

        return response()->json(['success' => true]);
    }
}
