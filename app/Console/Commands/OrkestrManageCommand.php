<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Models\Project;
use App\Models\Skill;
use Illuminate\Console\Command;

class OrkestrManageCommand extends Command
{
    protected $signature = 'orkestr:manage
                            {action : Action to perform (status, projects, agents, skills)}
                            {--project= : Filter by project ID}';

    protected $description = 'Manage agents, skills, and projects from the CLI';

    public function handle(): int
    {
        return match ($this->argument('action')) {
            'status' => $this->showStatus(),
            'projects' => $this->listProjects(),
            'agents' => $this->listAgents(),
            'skills' => $this->listSkills(),
            default => $this->error("Unknown action: {$this->argument('action')}") ?? self::FAILURE,
        };
    }

    protected function showStatus(): int
    {
        $this->info('Orkestr Status');
        $this->newLine();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Projects', Project::count()],
                ['Agents', Agent::count()],
                ['Skills', Skill::count()],
            ],
        );

        return self::SUCCESS;
    }

    protected function listProjects(): int
    {
        $projects = Project::orderBy('name')->get(['id', 'name', 'path', 'created_at']);

        $this->table(
            ['ID', 'Name', 'Path', 'Created'],
            $projects->map(fn ($p) => [$p->id, $p->name, $p->path, $p->created_at->format('Y-m-d')])->all(),
        );

        return self::SUCCESS;
    }

    protected function listAgents(): int
    {
        $agents = Agent::orderBy('name')->get(['id', 'name', 'slug', 'model', 'role']);

        $this->table(
            ['ID', 'Name', 'Slug', 'Model', 'Role'],
            $agents->map(fn ($a) => [$a->id, $a->name, $a->slug, $a->model, $a->role])->all(),
        );

        return self::SUCCESS;
    }

    protected function listSkills(): int
    {
        $query = Skill::orderBy('name');

        if ($projectId = $this->option('project')) {
            $query->where('project_id', $projectId);
        }

        $skills = $query->get(['id', 'name', 'slug', 'project_id', 'model']);

        $this->table(
            ['ID', 'Name', 'Slug', 'Project', 'Model'],
            $skills->map(fn ($s) => [$s->id, $s->name, $s->slug, $s->project_id, $s->model ?? '-'])->all(),
        );

        return self::SUCCESS;
    }
}
