<?php

namespace App\Services\EventBus;

use App\Models\EventLog;
use App\Models\EventSubscription;
use App\Models\EventTopic;
use Illuminate\Support\Facades\Redis;

class EventBusService
{
    /**
     * Redis stream key prefix.
     */
    protected const STREAM_PREFIX = 'eventbus:stream:';

    /**
     * Publish an event to a topic.
     *
     * Events are written to both Redis Streams (for real-time fan-out)
     * and the database event_log (for durability and querying).
     */
    public function publish(
        string $topicSlug,
        string $eventType,
        array $payload,
        ?string $publisherType = null,
        ?int $publisherId = null,
    ): void {
        $topic = EventTopic::where('slug', $topicSlug)->first();

        if (! $topic) {
            return;
        }

        $now = now();

        // 1. Write to Redis Stream for real-time delivery
        $streamKey = self::STREAM_PREFIX . $topic->slug;
        $streamData = [
            'topic_id' => $topic->id,
            'event_type' => $eventType,
            'payload' => json_encode($payload),
            'publisher_type' => $publisherType ?? '',
            'publisher_id' => $publisherId ?? 0,
            'published_at' => $now->toIso8601String(),
        ];

        try {
            Redis::xadd($streamKey, '*', $streamData);

            // Trim stream based on retention (approximate, Redis handles efficiently)
            $maxLen = max(1000, $topic->retention_hours * 100);
            Redis::xtrim($streamKey, 'MAXLEN', '~', $maxLen);
        } catch (\Throwable) {
            // Redis unavailable — fall through to DB-only mode
        }

        // 2. Write to database for durability and historical queries
        EventLog::create([
            'topic_id' => $topic->id,
            'publisher_type' => $publisherType,
            'publisher_id' => $publisherId,
            'event_type' => $eventType,
            'payload' => $payload,
            'published_at' => $now,
        ]);

        // 3. Fan-out to matching subscribers via queue
        $subscriptions = $topic->subscriptions()
            ->where('enabled', true)
            ->get();

        foreach ($subscriptions as $subscription) {
            if ($this->matchesFilter($payload, $subscription->filter_expression)) {
                $this->dispatchToSubscriber($subscription, $eventType, $payload);
            }
        }
    }

    /**
     * Create a subscription for a topic.
     *
     * Also creates a Redis consumer group so the subscriber gets
     * its own cursor into the stream.
     */
    public function subscribe(
        int $topicId,
        string $subscriberType,
        int $subscriberId,
        ?array $filterExpression = null,
    ): EventSubscription {
        $subscription = EventSubscription::create([
            'topic_id' => $topicId,
            'subscriber_type' => $subscriberType,
            'subscriber_id' => $subscriberId,
            'filter_expression' => $filterExpression,
            'enabled' => true,
        ]);

        // Create Redis consumer group for this subscription
        $topic = EventTopic::find($topicId);
        if ($topic) {
            $this->ensureConsumerGroup($topic->slug, $subscription->consumerGroupName());
        }

        return $subscription;
    }

    /**
     * Read pending events from a stream for a specific consumer group.
     *
     * @return array<int, array{id: string, event_type: string, payload: array, published_at: string}>
     */
    public function consume(string $topicSlug, string $consumerGroup, string $consumerName, int $count = 10): array
    {
        $streamKey = self::STREAM_PREFIX . $topicSlug;

        try {
            $this->ensureConsumerGroup($topicSlug, $consumerGroup);

            $messages = Redis::xreadgroup(
                $consumerGroup,
                $consumerName,
                [$streamKey => '>'],
                $count,
                0, // non-blocking
            );

            if (empty($messages[$streamKey])) {
                return [];
            }

            $events = [];
            foreach ($messages[$streamKey] as $id => $data) {
                $events[] = [
                    'id' => $id,
                    'event_type' => $data['event_type'] ?? '',
                    'payload' => json_decode($data['payload'] ?? '{}', true),
                    'publisher_type' => $data['publisher_type'] ?? null,
                    'publisher_id' => (int) ($data['publisher_id'] ?? 0) ?: null,
                    'published_at' => $data['published_at'] ?? '',
                ];
            }

            return $events;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Acknowledge that events have been processed by a consumer.
     */
    public function acknowledge(string $topicSlug, string $consumerGroup, array $messageIds): void
    {
        if (empty($messageIds)) {
            return;
        }

        $streamKey = self::STREAM_PREFIX . $topicSlug;

        try {
            Redis::xack($streamKey, $consumerGroup, ...$messageIds);
        } catch (\Throwable) {
            // Silently skip if Redis unavailable
        }
    }

    /**
     * Get stream info (length, consumer groups, pending counts).
     */
    public function streamInfo(string $topicSlug): array
    {
        $streamKey = self::STREAM_PREFIX . $topicSlug;

        try {
            $info = Redis::xinfo('STREAM', $streamKey);
            $groups = Redis::xinfo('GROUPS', $streamKey);

            return [
                'length' => $info['length'] ?? 0,
                'first_entry' => $info['first-entry'] ?? null,
                'last_entry' => $info['last-entry'] ?? null,
                'consumer_groups' => collect($groups)->map(fn ($g) => [
                    'name' => $g['name'],
                    'consumers' => $g['consumers'],
                    'pending' => $g['pending'],
                    'last_delivered_id' => $g['last-delivered-id'],
                ])->values()->all(),
            ];
        } catch (\Throwable) {
            return ['length' => 0, 'consumer_groups' => [], 'error' => 'Redis unavailable'];
        }
    }

    /**
     * Check if a payload matches a filter expression.
     */
    public function matchesFilter(array $payload, ?array $filter): bool
    {
        if (empty($filter)) {
            return true;
        }

        foreach ($filter as $key => $expectedValue) {
            $actualValue = data_get($payload, $key);

            if ($actualValue !== $expectedValue) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ensure a consumer group exists for a stream. Creates it if missing.
     */
    protected function ensureConsumerGroup(string $topicSlug, string $groupName): void
    {
        $streamKey = self::STREAM_PREFIX . $topicSlug;

        try {
            // MKSTREAM creates the stream if it doesn't exist
            Redis::xgroup('CREATE', $streamKey, $groupName, '$', 'MKSTREAM');
        } catch (\Throwable $e) {
            // BUSYGROUP = group already exists, which is fine
            if (! str_contains($e->getMessage(), 'BUSYGROUP')) {
                throw $e;
            }
        }
    }

    /**
     * Dispatch event to a subscriber based on type.
     * Pushes to a Redis list for async processing by subscriber workers.
     */
    protected function dispatchToSubscriber(EventSubscription $subscription, string $eventType, array $payload): void
    {
        $message = json_encode([
            'subscription_id' => $subscription->id,
            'subscriber_type' => $subscription->subscriber_type,
            'subscriber_id' => $subscription->subscriber_id,
            'event_type' => $eventType,
            'payload' => $payload,
            'dispatched_at' => now()->toIso8601String(),
        ]);

        try {
            Redis::rpush('eventbus:dispatch', $message);
        } catch (\Throwable) {
            // Redis unavailable — log or silently skip
        }
    }
}
