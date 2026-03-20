<?php

namespace App\Services\Negotiation;

use App\Models\Agent;
use App\Models\CapabilityAdvertisement;
use App\Services\Routing\CapabilityTracker;
use App\Services\Routing\LoadBalancer;
use Illuminate\Support\Collection;

class AdvertisementService
{
    public function __construct(
        protected CapabilityTracker $capabilityTracker,
        protected LoadBalancer $loadBalancer,
    ) {}

    /**
     * Create or update a capability advertisement for an agent in a project.
     */
    public function advertise(Agent $agent, int $projectId): CapabilityAdvertisement
    {
        // Ensure capabilities are tracked
        $capabilities = $this->capabilityTracker->getCapabilities($agent->id, $projectId);

        if ($capabilities->isEmpty()) {
            $capabilities = $this->capabilityTracker->inferCapabilities($agent, $projectId);
        }

        // Build capabilities array for the advertisement
        $capabilitiesArray = $capabilities->map(fn ($cap) => [
            'name' => $cap->capability,
            'proficiency' => (float) $cap->proficiency,
            'cost_per_task' => $cap->avg_cost_microcents ?? 1000,
        ])->values()->all();

        // Get current load info
        $currentLoad = $this->loadBalancer->getCurrentLoad($agent->id);
        $capacity = $this->loadBalancer->getCapacity($agent->id);
        $isAvailable = $this->loadBalancer->isAvailable($agent->id);

        // Determine availability status
        $availabilityStatus = 'available';
        if (! $isAvailable) {
            $availabilityStatus = $currentLoad >= $capacity ? 'busy' : 'offline';
        }

        // Create or update the advertisement
        $advertisement = CapabilityAdvertisement::updateOrCreate(
            [
                'agent_id' => $agent->id,
                'project_id' => $projectId,
            ],
            [
                'capabilities' => $capabilitiesArray,
                'availability_status' => $availabilityStatus,
                'max_concurrent_tasks' => $capacity,
                'current_load' => $currentLoad,
                'advertised_at' => now(),
                'expires_at' => now()->addMinutes(10),
            ]
        );

        return $advertisement;
    }

    /**
     * Refresh advertisements for all agents in a project.
     */
    public function refreshAll(int $projectId): int
    {
        $agents = Agent::whereHas('projects', fn ($q) => $q->where('projects.id', $projectId)->where('project_agent.is_enabled', true))
            ->get();

        $count = 0;

        foreach ($agents as $agent) {
            $this->advertise($agent, $projectId);
            $count++;
        }

        return $count;
    }

    /**
     * Get all active advertisements for a project with agent info.
     */
    public function getActive(int $projectId): Collection
    {
        return CapabilityAdvertisement::active()
            ->where('project_id', $projectId)
            ->with('agent:id,name,slug,role,icon,description')
            ->orderByDesc('advertised_at')
            ->get();
    }

    /**
     * Delete all expired advertisements.
     */
    public function cleanupExpired(): int
    {
        return CapabilityAdvertisement::where('expires_at', '<', now())->delete();
    }
}
