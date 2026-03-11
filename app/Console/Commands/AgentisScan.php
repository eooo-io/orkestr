<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\AgentisManifestService;
use Illuminate\Console\Command;

class AgentisScan extends Command
{
    protected $signature = 'agentis:scan
                            {project? : Project name, slug, or ID}
                            {--all : Scan all projects}';

    protected $description = 'Scan project .agentis/ directory and sync skills to database';

    public function handle(AgentisManifestService $manifestService): int
    {
        $projects = $this->resolveProjects();

        if ($projects->isEmpty()) {
            $this->error('No projects found.');

            return self::FAILURE;
        }

        foreach ($projects as $project) {
            $this->info("Scanning project: {$project->name}");

            $result = $manifestService->scanProject($project);

            $this->info("  Created: {$result['created']}, Updated: {$result['updated']}, Deleted: {$result['deleted']}");
        }

        return self::SUCCESS;
    }

    private function resolveProjects()
    {
        if ($this->option('all')) {
            return Project::all();
        }

        $identifier = $this->argument('project');

        if (! $identifier) {
            $projects = Project::all();

            if ($projects->count() === 1) {
                return $projects;
            }

            $name = $this->choice(
                'Which project?',
                $projects->pluck('name')->toArray(),
            );

            return $projects->where('name', $name)->values();
        }

        $project = Project::where('id', $identifier)
            ->orWhere('name', $identifier)
            ->orWhere('uuid', $identifier)
            ->first();

        return $project ? collect([$project]) : collect();
    }
}
