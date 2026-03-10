<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Services\WebhookDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public Webhook $webhook,
        public string $event,
        public array $payload,
    ) {}

    public function handle(WebhookDispatcher $dispatcher): void
    {
        $dispatcher->deliverWebhook($this->webhook, $this->event, $this->payload);
    }
}
