<?php

namespace App\Services\Notifications\Drivers;

use App\Models\NotificationChannel;
use Illuminate\Support\Facades\Http;

class DiscordDriver implements NotificationDriverInterface
{
    public function send(NotificationChannel $channel, string $eventType, array $payload): bool
    {
        $webhookUrl = $channel->config['webhook_url'] ?? null;

        if (! $webhookUrl) {
            return false;
        }

        $embed = [
            'title' => $payload['title'] ?? $eventType,
            'description' => $payload['message'] ?? json_encode($payload, JSON_PRETTY_PRINT),
            'color' => 5814783, // Blue
            'footer' => [
                'text' => "Event: {$eventType} | Channel: {$channel->name}",
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        $response = Http::post($webhookUrl, [
            'embeds' => [$embed],
        ]);

        return $response->successful();
    }
}
