<?php

namespace App\Jobs;

use App\Models\Agent;
use App\Services\AgentReputationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Nightly recompute of reputation scores across all agents.
 * Dispatched by the scheduler; safe to run ad-hoc.
 */
class RecomputeAgentReputationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?int $agentId = null) {}

    public function handle(AgentReputationService $service): void
    {
        $query = Agent::query();
        if ($this->agentId !== null) {
            $query->where('id', $this->agentId);
        }

        $query->chunkById(50, function ($agents) use ($service) {
            foreach ($agents as $agent) {
                $service->computeFor($agent);
            }
        });
    }
}
