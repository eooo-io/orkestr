<?php

namespace App\Services\Providers;

use App\Models\Project;
use Illuminate\Support\Collection;

interface ProviderDriverInterface
{
    /**
     * Sync all skills and composed agents to the provider's output format.
     *
     * @param  array<int, array{content: string, agent: array}>  $composedAgents
     * @param  array<int, string>  $resolvedBodies  Skill ID => resolved body with includes
     */
    public function sync(Project $project, Collection $skills, array $composedAgents = [], array $resolvedBodies = []): void;

    /**
     * Get the output file paths that this driver writes to.
     *
     * @return string[]
     */
    public function getOutputPaths(Project $project): array;

    /**
     * Remove all output files for this provider.
     */
    public function clean(Project $project): void;
}
