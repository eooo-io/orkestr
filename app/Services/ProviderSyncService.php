<?php

namespace App\Services;

use App\Models\Project;
use App\Services\Providers\ClineDriver;
use App\Services\Providers\ClaudeDriver;
use App\Services\Providers\CopilotDriver;
use App\Services\Providers\CursorDriver;
use App\Services\Providers\OpenAIDriver;
use App\Services\Providers\ProviderDriverInterface;
use App\Services\Providers\WindsurfDriver;

class ProviderSyncService
{
    protected array $drivers = [
        'claude' => ClaudeDriver::class,
        'cursor' => CursorDriver::class,
        'copilot' => CopilotDriver::class,
        'windsurf' => WindsurfDriver::class,
        'cline' => ClineDriver::class,
        'openai' => OpenAIDriver::class,
    ];

    public function __construct(
        protected AgentComposeService $composeService,
    ) {}

    public function getDriver(string $slug): ProviderDriverInterface
    {
        $driverClass = $this->drivers[$slug] ?? null;

        if (! $driverClass) {
            throw new \InvalidArgumentException("Unknown provider: {$slug}");
        }

        return new $driverClass;
    }

    public function syncProject(Project $project): void
    {
        $project->loadMissing(['providers', 'skills.tags']);
        $skills = $project->skills;

        // Compose enabled agents
        $composedAgents = $this->composeService->composeAll($project);

        foreach ($project->providers as $provider) {
            $driver = $this->getDriver($provider->provider_slug);
            $driver->sync($project, $skills, $composedAgents);
        }

        $project->update(['synced_at' => now()]);
    }
}
