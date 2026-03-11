<?php

namespace App\Console\Commands;

use App\Models\Project;
use Illuminate\Console\Command;

class AgentisList extends Command
{
    protected $signature = 'agentis:list
                            {project? : Project name, slug, or ID}
                            {--tags= : Filter by tags (comma-separated)}
                            {--model= : Filter by model}
                            {--json : Output as JSON}';

    protected $description = 'List skills in a project';

    public function handle(): int
    {
        $project = $this->resolveProject();

        if (! $project) {
            $this->error('Project not found.');

            return self::FAILURE;
        }

        $query = $project->skills()->with('tags');

        if ($tags = $this->option('tags')) {
            $tagNames = array_map('trim', explode(',', $tags));
            $query->whereHas('tags', fn ($q) => $q->whereIn('name', $tagNames));
        }

        if ($model = $this->option('model')) {
            $query->where('model', $model);
        }

        $skills = $query->orderBy('name')->get();

        if ($skills->isEmpty()) {
            $this->info('No skills found.');

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line($skills->toJson(JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info("Project: {$project->name} ({$skills->count()} skills)");
        $this->newLine();

        $rows = $skills->map(fn ($skill) => [
            $skill->slug,
            $skill->name,
            $skill->model ?? '-',
            $skill->tags->pluck('name')->join(', ') ?: '-',
            $skill->includes ? implode(', ', $skill->includes) : '-',
            ! empty($skill->conditions) ? 'Yes' : '-',
        ]);

        $this->table(
            ['Slug', 'Name', 'Model', 'Tags', 'Includes', 'Conditional'],
            $rows,
        );

        return self::SUCCESS;
    }

    private function resolveProject(): ?Project
    {
        $identifier = $this->argument('project');

        if (! $identifier) {
            $projects = Project::all();

            if ($projects->count() === 1) {
                return $projects->first();
            }

            $name = $this->choice(
                'Which project?',
                $projects->pluck('name')->toArray(),
            );

            return $projects->firstWhere('name', $name);
        }

        return Project::where('id', $identifier)
            ->orWhere('name', $identifier)
            ->orWhere('uuid', $identifier)
            ->first();
    }
}
