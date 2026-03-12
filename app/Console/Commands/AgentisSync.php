<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\ProviderSyncService;
use Illuminate\Console\Command;

class AgentisSync extends Command
{
    protected $signature = 'agentis:sync
                            {project? : Project name, slug, or ID}
                            {--provider= : Sync only a specific provider (claude, cursor, copilot, windsurf, cline, openai, openclaw)}
                            {--preview : Show what would change without writing files}
                            {--all : Sync all projects}';

    protected $description = 'Sync skills to provider-specific config files';

    public function handle(ProviderSyncService $syncService): int
    {
        $projects = $this->resolveProjects();

        if ($projects->isEmpty()) {
            $this->error('No projects found.');

            return self::FAILURE;
        }

        foreach ($projects as $project) {
            $this->info("Syncing project: {$project->name}");

            $providerFilter = $this->option('provider');

            if ($this->option('preview')) {
                $preview = $syncService->preview($project);

                if (empty($preview)) {
                    $this->line('  No changes detected.');

                    continue;
                }

                $rows = collect($preview)->map(fn ($file) => [
                    $file['provider'],
                    basename($file['path']),
                    $file['status'],
                ]);

                $this->table(['Provider', 'File', 'Status'], $rows);

                continue;
            }

            $syncService->syncProject($project, $providerFilter);

            $project->update(['synced_at' => now()]);

            $providers = $project->providers->pluck('provider_slug')->join(', ');
            $skillCount = $project->skills()->count();
            $this->info("  Synced {$skillCount} skill(s) to: {$providers}");
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

        if (! $project) {
            return collect();
        }

        return collect([$project]);
    }
}
