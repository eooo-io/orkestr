<?php

namespace App\Services;

use App\Jobs\DispatchWebhookJob;
use App\Models\Project;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;

class WebhookDispatcher
{
    /**
     * Find all active webhooks for this project matching the event and queue delivery jobs.
     */
    public function dispatch(string $event, Project $project, array $payload): void
    {
        $webhooks = Webhook::where('project_id', $project->id)
            ->active()
            ->forEvent($event)
            ->get();

        foreach ($webhooks as $webhook) {
            DispatchWebhookJob::dispatch($webhook, $event, $payload);
        }
    }

    /**
     * Send the HTTP POST to the webhook URL and record the delivery.
     */
    public function deliverWebhook(Webhook $webhook, string $event, array $payload): void
    {
        $body = [
            'event' => $event,
            'payload' => $payload,
            'timestamp' => now()->toIso8601String(),
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'X-Agentis-Event' => $event,
        ];

        if ($webhook->secret) {
            $signature = hash_hmac('sha256', json_encode($body), $webhook->secret);
            $headers['X-Agentis-Signature'] = $signature;
        }

        $start = microtime(true);
        $responseStatus = null;
        $responseBody = null;

        try {
            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->post($webhook->url, $body);

            $responseStatus = $response->status();
            $responseBody = $response->body();
        } catch (\Throwable $e) {
            $responseStatus = 0;
            $responseBody = $e->getMessage();
        }

        $durationMs = (int) ((microtime(true) - $start) * 1000);

        WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event' => $event,
            'payload' => $body,
            'response_status' => $responseStatus,
            'response_body' => $responseBody,
            'duration_ms' => $durationMs,
            'created_at' => now(),
        ]);

        $webhook->update([
            'last_triggered_at' => now(),
            'last_status' => $responseStatus,
        ]);
    }
}
