<?php

namespace App\Jobs;

use App\Models\Agent;
use App\Services\PatternExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExtractMemoryPatternsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?int $agentId = null) {}

    public function handle(PatternExtractionService $service): void
    {
        $query = Agent::query()->whereNotNull('owner_user_id');
        if ($this->agentId !== null) {
            $query->where('id', $this->agentId);
        }

        $query->chunkById(25, function ($agents) use ($service) {
            foreach ($agents as $agent) {
                $service->extractForAgent($agent);
            }
        });
    }
}
