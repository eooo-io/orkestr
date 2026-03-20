<?php

namespace App\Services\Notifications\Drivers;

use App\Models\NotificationChannel;

interface NotificationDriverInterface
{
    /**
     * Send a notification through the channel.
     *
     * @return bool true on success, false on failure
     */
    public function send(NotificationChannel $channel, string $eventType, array $payload): bool;
}
