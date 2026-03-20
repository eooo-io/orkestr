<?php

namespace App\Services\Notifications\Drivers;

use App\Models\NotificationChannel;
use Illuminate\Support\Facades\Http;

class WebhookDriver implements NotificationDriverInterface
{
    public function send(NotificationChannel $channel, string $eventType, array $payload): bool
    {
        $webhookUrl = $channel->config['webhook_url'] ?? null;

        if (! $webhookUrl) {
            return false;
        }

        $headers = [];

        if (! empty($channel->config['api_token'])) {
            $headers['Authorization'] = 'Bearer ' . $channel->config['api_token'];
        }

        $body = [
            'event_type' => $eventType,
            'channel' => $channel->name,
            'payload' => $payload,
            'timestamp' => now()->toIso8601String(),
        ];

        $response = Http::withHeaders($headers)->post($webhookUrl, $body);

        return $response->successful();
    }
}
