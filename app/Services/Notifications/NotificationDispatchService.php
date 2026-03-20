<?php

namespace App\Services\Notifications;

use App\Models\NotificationChannel;
use App\Models\NotificationDelivery;
use App\Services\Notifications\Drivers\DiscordDriver;
use App\Services\Notifications\Drivers\EmailDriver;
use App\Services\Notifications\Drivers\NotificationDriverInterface;
use App\Services\Notifications\Drivers\SlackDriver;
use App\Services\Notifications\Drivers\TeamsDriver;
use App\Services\Notifications\Drivers\WebhookDriver;

class NotificationDispatchService
{
    protected array $drivers = [
        'slack' => SlackDriver::class,
        'teams' => TeamsDriver::class,
        'email' => EmailDriver::class,
        'discord' => DiscordDriver::class,
        'webhook' => WebhookDriver::class,
    ];

    /**
     * Dispatch a notification through a specific channel.
     */
    public function dispatch(NotificationChannel $channel, string $eventType, array $payload): void
    {
        $driverClass = $this->drivers[$channel->type] ?? null;

        if (! $driverClass) {
            $this->createDelivery($channel, $eventType, $payload, 'failed', "Unknown driver type: {$channel->type}");

            return;
        }

        /** @var NotificationDriverInterface $driver */
        $driver = new $driverClass;

        try {
            $success = $driver->send($channel, $eventType, $payload);

            $this->createDelivery(
                $channel,
                $eventType,
                $payload,
                $success ? 'sent' : 'failed',
                $success ? null : 'Driver returned false',
            );
        } catch (\Throwable $e) {
            $this->createDelivery($channel, $eventType, $payload, 'failed', $e->getMessage());
        }
    }

    /**
     * Dispatch a notification to all enabled channels for an organization.
     */
    public function dispatchToAll(int $organizationId, string $eventType, array $payload): void
    {
        $channels = NotificationChannel::where('organization_id', $organizationId)
            ->where('enabled', true)
            ->get();

        foreach ($channels as $channel) {
            $this->dispatch($channel, $eventType, $payload);
        }
    }

    protected function createDelivery(
        NotificationChannel $channel,
        string $eventType,
        array $payload,
        string $status,
        ?string $errorMessage = null,
    ): NotificationDelivery {
        return NotificationDelivery::create([
            'channel_id' => $channel->id,
            'event_type' => $eventType,
            'payload' => $payload,
            'status' => $status,
            'error_message' => $errorMessage,
            'sent_at' => $status === 'sent' ? now() : null,
        ]);
    }
}
