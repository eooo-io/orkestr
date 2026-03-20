<?php

namespace App\Services\Notifications\Drivers;

use App\Models\NotificationChannel;
use Illuminate\Support\Facades\Mail;

class EmailDriver implements NotificationDriverInterface
{
    public function send(NotificationChannel $channel, string $eventType, array $payload): bool
    {
        $addresses = $channel->config['email_addresses'] ?? [];

        if (empty($addresses)) {
            return false;
        }

        if (is_string($addresses)) {
            $addresses = array_map('trim', explode(',', $addresses));
        }

        $subject = $payload['title'] ?? "Notification: {$eventType}";
        $body = $payload['message'] ?? json_encode($payload, JSON_PRETTY_PRINT);

        try {
            Mail::raw($body, function ($message) use ($addresses, $subject) {
                $message->to($addresses)->subject($subject);
            });

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
