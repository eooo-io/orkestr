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
use Illuminate\Support\Facades\File;

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
        protected SkillCompositionService $compositionService,
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

        // Pre-resolve skill includes for sync
        $resolvedBodies = [];
        foreach ($skills as $skill) {
            $resolvedBodies[$skill->id] = $this->compositionService->resolve($skill);
        }

        // Compose enabled agents
        $composedAgents = $this->composeService->composeAll($project);

        foreach ($project->providers as $provider) {
            $driver = $this->getDriver($provider->provider_slug);
            $driver->sync($project, $skills, $composedAgents, $resolvedBodies);
        }

        $project->update(['synced_at' => now()]);
    }

    /**
     * Preview what a sync would produce without writing any files.
     *
     * @return array<int, array{path: string, provider: string, current: string|null, proposed: string, status: string}>
     */
    public function preview(Project $project): array
    {
        $project->loadMissing(['providers', 'skills.tags']);
        $skills = $project->skills;

        // Pre-resolve skill includes
        $resolvedBodies = [];
        foreach ($skills as $skill) {
            $resolvedBodies[$skill->id] = $this->compositionService->resolve($skill);
        }

        // Compose enabled agents
        $composedAgents = $this->composeService->composeAll($project);

        $preview = [];

        foreach ($project->providers as $provider) {
            $driver = $this->getDriver($provider->provider_slug);
            $proposedFiles = $driver->generate($project, $skills, $composedAgents, $resolvedBodies);

            // Track existing files for this provider to detect deletions
            $existingPaths = $driver->getOutputPaths($project);
            $proposedPaths = array_keys($proposedFiles);

            foreach ($proposedFiles as $path => $proposed) {
                $current = File::exists($path) ? File::get($path) : null;

                if ($current === null) {
                    $status = 'added';
                } elseif ($current === $proposed) {
                    $status = 'unchanged';
                } else {
                    $status = 'modified';
                }

                $preview[] = [
                    'path' => $path,
                    'provider' => $provider->provider_slug,
                    'current' => $current,
                    'proposed' => $proposed,
                    'status' => $status,
                ];
            }

            // Check for files that exist on disk but won't be in the new sync (deleted)
            foreach ($existingPaths as $existingPath) {
                if (! in_array($existingPath, $proposedPaths, true)) {
                    $preview[] = [
                        'path' => $existingPath,
                        'provider' => $provider->provider_slug,
                        'current' => File::exists($existingPath) ? File::get($existingPath) : null,
                        'proposed' => '',
                        'status' => 'deleted',
                    ];
                }
            }
        }

        return $preview;
    }
}
