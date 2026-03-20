<?php

namespace App\Http\Controllers;

use App\Models\NotificationChannel;
use App\Models\Organization;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationChannelController extends Controller
{
    /**
     * GET /api/organizations/{organization}/notification-channels
     */
    public function index(Organization $organization): JsonResponse
    {
        $channels = NotificationChannel::where('organization_id', $organization->id)
            ->withCount('deliveries')
            ->orderBy('name')
            ->get()
            ->map(fn (NotificationChannel $ch) => $this->formatChannel($ch));

        return response()->json(['data' => $channels]);
    }

    /**
     * POST /api/organizations/{organization}/notification-channels
     */
    public function store(Request $request, Organization $organization): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:slack,teams,email,discord,webhook',
            'config' => 'required|array',
            'enabled' => 'nullable|boolean',
        ]);

        $channel = NotificationChannel::create([
            'organization_id' => $organization->id,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'config' => $validated['config'],
            'enabled' => $validated['enabled'] ?? true,
        ]);

        $channel->loadCount('deliveries');

        return response()->json(['data' => $this->formatChannel($channel)], 201);
    }

    /**
     * PUT /api/notification-channels/{notification_channel}
     */
    public function update(Request $request, NotificationChannel $notificationChannel): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'type' => 'nullable|string|in:slack,teams,email,discord,webhook',
            'config' => 'nullable|array',
            'enabled' => 'nullable|boolean',
        ]);

        $notificationChannel->update(array_filter($validated, fn ($v) => $v !== null));
        $notificationChannel->loadCount('deliveries');

        return response()->json(['data' => $this->formatChannel($notificationChannel)]);
    }

    /**
     * DELETE /api/notification-channels/{notification_channel}
     */
    public function destroy(NotificationChannel $notificationChannel): JsonResponse
    {
        $notificationChannel->delete();

        return response()->json(['message' => 'Notification channel deleted']);
    }

    /**
     * POST /api/notification-channels/{notification_channel}/test
     */
    public function test(NotificationChannel $notificationChannel): JsonResponse
    {
        $service = new NotificationDispatchService;

        $service->dispatch($notificationChannel, 'test', [
            'title' => 'Test Notification',
            'message' => "This is a test notification from channel \"{$notificationChannel->name}\".",
        ]);

        $latestDelivery = $notificationChannel->deliveries()->latest()->first();

        return response()->json([
            'data' => [
                'success' => $latestDelivery && $latestDelivery->status === 'sent',
                'status' => $latestDelivery?->status ?? 'unknown',
                'error' => $latestDelivery?->error_message,
            ],
        ]);
    }

    /**
     * GET /api/notification-channels/{notification_channel}/deliveries
     */
    public function deliveries(NotificationChannel $notificationChannel): JsonResponse
    {
        $deliveries = $notificationChannel->deliveries()
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'event_type' => $d->event_type,
                'status' => $d->status,
                'error_message' => $d->error_message,
                'sent_at' => $d->sent_at?->toIso8601String(),
                'created_at' => $d->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $deliveries]);
    }

    private function formatChannel(NotificationChannel $ch): array
    {
        return [
            'id' => $ch->id,
            'organization_id' => $ch->organization_id,
            'name' => $ch->name,
            'slug' => $ch->slug,
            'type' => $ch->type,
            'config' => $ch->config,
            'enabled' => $ch->enabled,
            'verified_at' => $ch->verified_at?->toIso8601String(),
            'deliveries_count' => $ch->deliveries_count ?? 0,
            'created_at' => $ch->created_at?->toIso8601String(),
            'updated_at' => $ch->updated_at?->toIso8601String(),
        ];
    }
}
