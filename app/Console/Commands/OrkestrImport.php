<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\ProviderImportService;
use Illuminate\Console\Command;

class OrkestrImport extends Command
{
    protected $signature = 'orkestr:import
                            {path : Path to project directory to import from}
                            {--into= : Target Orkestr project name or ID}
                            {--provider= : Import only from a specific provider}
                            {--dry-run : Show what would be imported without making changes}';

    protected $description = 'Reverse-sync: import existing provider config files into Orkestr skills';

    public function handle(ProviderImportService $importService): int
    {
        $sourcePath = realpath($this->argument('path'));

        if (! $sourcePath || ! is_dir($sourcePath)) {
            $this->error("Directory not found: {$this->argument('path')}");

            return self::FAILURE;
        }

        $project = $this->resolveTargetProject();

        if (! $project) {
            return self::FAILURE;
        }

        $providerFilter = $this->option('provider');
        $dryRun = $this->option('dry-run');

        $this->info("Scanning {$sourcePath} for provider config files...");

        $detected = $importService->detect($sourcePath, $providerFilter);

        if (empty($detected)) {
            $this->warn('No provider config files found.');

            return self::SUCCESS;
        }

        $totalSkills = 0;
        foreach ($detected as $provider => $skills) {
            $count = count($skills);
            $totalSkills += $count;
            $this->line("  [{$provider}] {$count} skill(s) found");
        }

        if ($dryRun) {
            $this->newLine();
            $this->info('Dry run — skills that would be imported:');

            $rows = [];
            foreach ($detected as $provider => $skills) {
                foreach ($skills as $skill) {
                    $rows[] = [
                        $provider,
                        $skill['name'],
                        $skill['slug'],
                        strlen($skill['body']) . ' chars',
                    ];
                }
            }

            $this->table(['Provider', 'Name', 'Slug', 'Body'], $rows);

            return self::SUCCESS;
        }

        if (! $this->confirm("Import {$totalSkills} skill(s) into project \"{$project->name}\"?")) {
            return self::SUCCESS;
        }

        $result = $importService->import($project, $detected);

        $this->info("Imported: {$result['created']} new, {$result['skipped']} skipped (already exist)");

        return self::SUCCESS;
    }

    private function resolveTargetProject(): ?Project
    {
        $identifier = $this->option('into');

        if ($identifier) {
            $project = Project::where('id', $identifier)
                ->orWhere('name', $identifier)
                ->orWhere('uuid', $identifier)
                ->first();

            if (! $project) {
                $this->error("Project not found: {$identifier}");

                return null;
            }

            return $project;
        }

        $projects = Project::all();

        if ($projects->isEmpty()) {
            $this->error('No projects exist. Create one first.');

            return null;
        }

        if ($projects->count() === 1) {
            return $projects->first();
        }

        $name = $this->choice(
            'Import into which project?',
            $projects->pluck('name')->toArray(),
        );

        return $projects->firstWhere('name', $name);
    }
}
