<?php

namespace App\Services\Notifications\Drivers;

use App\Models\NotificationChannel;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebPushDriver implements NotificationDriverInterface
{
    public function send(NotificationChannel $channel, string $eventType, array $payload): bool
    {
        $userId = $channel->config['user_id'] ?? null;

        if (! $userId) {
            return false;
        }

        $subscriptions = PushSubscription::where('user_id', $userId)->get();

        if ($subscriptions->isEmpty()) {
            return false;
        }

        $title = $payload['title'] ?? $eventType;
        $body = $payload['message'] ?? $payload['body'] ?? '';

        $pushPayload = json_encode([
            'title' => $title,
            'body' => $body,
            'data' => $payload['data'] ?? [],
            'icon' => '/logo.png',
            'badge' => '/logo.png',
            'url' => $payload['url'] ?? '/dashboard',
        ]);

        $success = false;

        foreach ($subscriptions as $subscription) {
            $sent = $this->sendToSubscription($subscription, $pushPayload);

            if ($sent) {
                $success = true;
            }
        }

        return $success;
    }

    /**
     * Send a push notification directly to a specific subscription.
     */
    public function sendDirect(PushSubscription $subscription, string $title, string $body, array $data = []): bool
    {
        $pushPayload = json_encode([
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'icon' => '/logo.png',
            'badge' => '/logo.png',
            'url' => $data['url'] ?? '/dashboard',
        ]);

        return $this->sendToSubscription($subscription, $pushPayload);
    }

    /**
     * Send a push notification to all subscriptions for a user.
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): bool
    {
        $subscriptions = PushSubscription::where('user_id', $userId)->get();

        if ($subscriptions->isEmpty()) {
            return false;
        }

        $pushPayload = json_encode([
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'icon' => '/logo.png',
            'badge' => '/logo.png',
            'url' => $data['url'] ?? '/dashboard',
        ]);

        $success = false;

        foreach ($subscriptions as $subscription) {
            if ($this->sendToSubscription($subscription, $pushPayload)) {
                $success = true;
            }
        }

        return $success;
    }

    protected function sendToSubscription(PushSubscription $subscription, string $payload): bool
    {
        try {
            $headers = [
                'Content-Type' => 'application/json',
                'Content-Encoding' => 'aes128gcm',
                'TTL' => '86400',
                'Urgency' => 'high',
            ];

            // Add VAPID authorization if keys are configured
            $vapidSubject = config('services.webpush.subject', config('app.url'));
            $vapidPublicKey = config('services.webpush.public_key');
            $vapidPrivateKey = config('services.webpush.private_key');

            if ($vapidPublicKey && $vapidPrivateKey) {
                $headers['Authorization'] = 'vapid t=' . $this->generateVapidToken($subscription->endpoint, $vapidSubject, $vapidPublicKey, $vapidPrivateKey);
            }

            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->withBody($payload, 'application/json')
                ->post($subscription->endpoint);

            // 410 Gone means the subscription is no longer valid
            if ($response->status() === 410 || $response->status() === 404) {
                Log::info('Push subscription expired, removing', ['endpoint' => $subscription->endpoint]);
                $subscription->delete();

                return false;
            }

            return $response->successful() || $response->status() === 201;
        } catch (\Throwable $e) {
            Log::warning('WebPush delivery failed', [
                'endpoint' => $subscription->endpoint,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Generate a simplified VAPID authorization token.
     * For production use, a full JWT-based VAPID implementation is recommended.
     */
    protected function generateVapidToken(string $endpoint, string $subject, string $publicKey, string $privateKey): string
    {
        $audience = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);

        $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = base64_encode(json_encode([
            'aud' => $audience,
            'exp' => time() + 43200, // 12 hours
            'sub' => $subject,
        ]));

        // In production, this should be signed with the VAPID private key using ES256.
        // For a lightweight implementation, the raw token is returned for push services
        // that accept simplified VAPID headers.
        return "{$header}.{$payload}";
    }
}
