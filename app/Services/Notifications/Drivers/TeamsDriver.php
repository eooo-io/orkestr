<?php

namespace App\Services\Notifications\Drivers;

use App\Models\NotificationChannel;
use Illuminate\Support\Facades\Http;

class TeamsDriver implements NotificationDriverInterface
{
    public function send(NotificationChannel $channel, string $eventType, array $payload): bool
    {
        $webhookUrl = $channel->config['webhook_url'] ?? null;

        if (! $webhookUrl) {
            return false;
        }

        $card = [
            'type' => 'message',
            'attachments' => [
                [
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'content' => [
                        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                        'type' => 'AdaptiveCard',
                        'version' => '1.4',
                        'body' => [
                            [
                                'type' => 'TextBlock',
                                'size' => 'Large',
                                'weight' => 'Bolder',
                                'text' => $payload['title'] ?? $eventType,
                            ],
                            [
                                'type' => 'TextBlock',
                                'text' => $payload['message'] ?? json_encode($payload, JSON_PRETTY_PRINT),
                                'wrap' => true,
                            ],
                            [
                                'type' => 'FactSet',
                                'facts' => [
                                    ['title' => 'Event', 'value' => $eventType],
                                    ['title' => 'Channel', 'value' => $channel->name],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::post($webhookUrl, $card);

        return $response->successful();
    }
}
