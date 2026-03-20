<?php

namespace App\Http\Controllers;

use App\Models\EventLog;
use App\Models\EventSubscription;
use App\Models\EventTopic;
use App\Models\Organization;
use App\Services\EventBus\EventBusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventBusController extends Controller
{
    /**
     * GET /api/organizations/{organization}/event-topics
     */
    public function topicIndex(Organization $organization): JsonResponse
    {
        $topics = EventTopic::where('organization_id', $organization->id)
            ->withCount(['subscriptions', 'events'])
            ->orderBy('name')
            ->get()
            ->map(fn (EventTopic $t) => $this->formatTopic($t));

        return response()->json(['data' => $topics]);
    }

    /**
     * POST /api/organizations/{organization}/event-topics
     */
    public function topicStore(Request $request, Organization $organization): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'schema' => 'nullable|array',
            'retention_hours' => 'nullable|integer|min:1|max:8760', // max 1 year
        ]);

        $topic = EventTopic::create([
            'organization_id' => $organization->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'schema' => $validated['schema'] ?? null,
            'retention_hours' => $validated['retention_hours'] ?? 72,
        ]);

        $topic->loadCount(['subscriptions', 'events']);

        return response()->json(['data' => $this->formatTopic($topic)], 201);
    }

    /**
     * PUT /api/event-topics/{event_topic}
     */
    public function topicUpdate(Request $request, EventTopic $eventTopic): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'schema' => 'nullable|array',
            'retention_hours' => 'nullable|integer|min:1|max:8760',
        ]);

        $eventTopic->update(array_filter($validated, fn ($v) => $v !== null));
        $eventTopic->loadCount(['subscriptions', 'events']);

        return response()->json(['data' => $this->formatTopic($eventTopic)]);
    }

    /**
     * DELETE /api/event-topics/{event_topic}
     */
    public function topicDestroy(EventTopic $eventTopic): JsonResponse
    {
        $eventTopic->delete();

        return response()->json(['message' => 'Event topic deleted']);
    }

    /**
     * GET /api/event-topics/{event_topic}/events
     */
    public function eventLog(Request $request, EventTopic $eventTopic): JsonResponse
    {
        $events = $eventTopic->events()
            ->orderByDesc('published_at')
            ->paginate($request->input('per_page', 50));

        return response()->json([
            'data' => collect($events->items())->map(fn (EventLog $e) => [
                'id' => $e->id,
                'event_type' => $e->event_type,
                'publisher_type' => $e->publisher_type,
                'publisher_id' => $e->publisher_id,
                'payload' => $e->payload,
                'published_at' => $e->published_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    /**
     * GET /api/event-topics/{event_topic}/subscriptions
     */
    public function subscriptionIndex(EventTopic $eventTopic): JsonResponse
    {
        $subscriptions = $eventTopic->subscriptions()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (EventSubscription $s) => $this->formatSubscription($s));

        return response()->json(['data' => $subscriptions]);
    }

    /**
     * POST /api/event-topics/{event_topic}/subscriptions
     */
    public function subscriptionStore(Request $request, EventTopic $eventTopic): JsonResponse
    {
        $validated = $request->validate([
            'subscriber_type' => 'required|string|in:agent,webhook,channel',
            'subscriber_id' => 'required|integer',
            'filter_expression' => 'nullable|array',
            'enabled' => 'nullable|boolean',
        ]);

        $service = new EventBusService;

        $subscription = $service->subscribe(
            $eventTopic->id,
            $validated['subscriber_type'],
            $validated['subscriber_id'],
            $validated['filter_expression'] ?? null,
        );

        if (isset($validated['enabled'])) {
            $subscription->update(['enabled' => $validated['enabled']]);
        }

        return response()->json(['data' => $this->formatSubscription($subscription)], 201);
    }

    /**
     * DELETE /api/event-subscriptions/{event_subscription}
     */
    public function subscriptionDestroy(EventSubscription $eventSubscription): JsonResponse
    {
        $eventSubscription->delete();

        return response()->json(['message' => 'Subscription deleted']);
    }

    /**
     * GET /api/event-topics/{event_topic}/stream-info
     * Redis Stream info: length, consumer groups, pending counts.
     */
    public function streamInfo(EventTopic $eventTopic): JsonResponse
    {
        $service = new EventBusService;

        return response()->json([
            'data' => $service->streamInfo($eventTopic->slug),
        ]);
    }

    /**
     * POST /api/event-topics/{event_topic}/publish
     * Manually publish an event (for testing or external ingestion).
     */
    public function publish(Request $request, EventTopic $eventTopic): JsonResponse
    {
        $validated = $request->validate([
            'event_type' => 'required|string|max:100',
            'payload' => 'required|array',
        ]);

        $service = new EventBusService;
        $service->publish(
            $eventTopic->slug,
            $validated['event_type'],
            $validated['payload'],
            'manual',
            $request->user()?->id,
        );

        return response()->json(['message' => 'Event published'], 201);
    }

    private function formatTopic(EventTopic $topic): array
    {
        return [
            'id' => $topic->id,
            'organization_id' => $topic->organization_id,
            'name' => $topic->name,
            'slug' => $topic->slug,
            'description' => $topic->description,
            'schema' => $topic->schema,
            'retention_hours' => $topic->retention_hours,
            'subscriptions_count' => $topic->subscriptions_count ?? 0,
            'events_count' => $topic->events_count ?? 0,
            'created_at' => $topic->created_at?->toIso8601String(),
            'updated_at' => $topic->updated_at?->toIso8601String(),
        ];
    }

    private function formatSubscription(EventSubscription $sub): array
    {
        return [
            'id' => $sub->id,
            'topic_id' => $sub->topic_id,
            'subscriber_type' => $sub->subscriber_type,
            'subscriber_id' => $sub->subscriber_id,
            'filter_expression' => $sub->filter_expression,
            'enabled' => $sub->enabled,
            'created_at' => $sub->created_at?->toIso8601String(),
            'updated_at' => $sub->updated_at?->toIso8601String(),
        ];
    }
}
