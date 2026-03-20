<?php

namespace App\Services\Notifications\Drivers;

use App\Models\NotificationChannel;
use Illuminate\Support\Facades\Http;

class SlackDriver implements NotificationDriverInterface
{
    public function send(NotificationChannel $channel, string $eventType, array $payload): bool
    {
        $webhookUrl = $channel->config['webhook_url'] ?? null;

        if (! $webhookUrl) {
            return false;
        }

        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => $payload['title'] ?? $eventType,
                ],
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $payload['message'] ?? json_encode($payload, JSON_PRETTY_PRINT),
                ],
            ],
            [
                'type' => 'context',
                'elements' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "Event: *{$eventType}* | Channel: *{$channel->name}*",
                    ],
                ],
            ],
        ];

        $response = Http::post($webhookUrl, [
            'blocks' => $blocks,
            'text' => $payload['title'] ?? $eventType,
        ]);

        return $response->successful();
    }
}
