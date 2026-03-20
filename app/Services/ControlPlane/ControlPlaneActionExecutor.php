<?php

namespace App\Services\ControlPlane;

use App\Models\Agent;
use App\Models\AgentProcess;
use App\Models\AppSetting;
use App\Models\ControlPlaneSession;
use App\Models\ExecutionRun;
use App\Models\Project;
use App\Models\ProjectAgent;
use App\Models\Skill;
use App\Models\User;
use App\Services\DaemonProcessManager;
use App\Services\LLM\LLMProviderFactory;
use App\Services\SkillCompositionService;
use Illuminate\Support\Facades\DB;

class ControlPlaneActionExecutor
{
    public function __construct(
        protected LLMProviderFactory $providerFactory,
        protected SkillCompositionService $compositionService,
    ) {}

    /**
     * Execute a tool call and return the structured result.
     */
    public function execute(string $toolName, array $params, User $user, ?ControlPlaneSession $session = null): array
    {
        try {
            return match ($toolName) {
                // Agent management
                'list_agents' => $this->listAgents(),
                'create_agent' => $this->createAgent($params),
                'restart_agent' => $this->restartAgent($params),
                'stop_agent' => $this->stopAgent($params),
                'toggle_agent' => $this->toggleAgent($params),

                // Skill management
                'list_skills' => $this->listSkills($params),
                'search_skills' => $this->searchSkills($params),
                'create_skill' => $this->createSkill($params, $user),
                'run_skill_test' => $this->runSkillTest($params),

                // Execution
                'start_execution' => $this->startExecution($params, $user),
                'list_recent_runs' => $this->listRecentRuns($params),
                'list_failures' => $this->listFailures($params),
                'cancel_run' => $this->cancelRun($params),

                // System
                'view_diagnostics' => $this->viewDiagnostics(),
                'provider_health' => $this->providerHealth(),
                'fleet_status' => $this->fleetStatus(),

                // Project
                'list_projects' => $this->listProjects(),
                'switch_project' => $this->switchProject($params, $session),
                'view_graph' => $this->viewGraph($params),

                default => ['error' => "Unknown tool: {$toolName}"],
            };
        } catch (\Throwable $e) {
            return [
                'error' => $e->getMessage(),
                'tool' => $toolName,
            ];
        }
    }

    // ── Agent Management ────────────────────────────────────────────

    protected function listAgents(): array
    {
        $agents = Agent::orderBy('sort_order')
            ->select('id', 'name', 'slug', 'role', 'description', 'model', 'icon')
            ->get();

        return [
            'agents' => $agents->map(fn (Agent $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'role' => $a->role,
                'description' => $a->description,
                'model' => $a->model,
                'icon' => $a->icon,
            ])->toArray(),
            'count' => $agents->count(),
        ];
    }

    protected function createAgent(array $params): array
    {
        $agent = Agent::create([
            'name' => $params['name'],
            'role' => $params['role'],
            'description' => $params['description'] ?? null,
            'model' => $params['model'] ?? 'claude-sonnet-4-6',
            'planning_mode' => 'plan_then_act',
            'context_strategy' => 'full',
            'max_iterations' => 10,
            'can_delegate' => false,
        ]);

        return [
            'created' => true,
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'slug' => $agent->slug,
                'role' => $agent->role,
                'model' => $agent->model,
            ],
        ];
    }

    protected function restartAgent(array $params): array
    {
        $agent = Agent::findOrFail($params['agent_id']);
        $process = AgentProcess::where('agent_id', $agent->id)
            ->whereIn('status', ['running', 'sleeping'])
            ->latest()
            ->first();

        if (! $process) {
            return ['error' => "No active process found for agent '{$agent->name}'"];
        }

        $manager = app(DaemonProcessManager::class);
        $newProcess = $manager->restart($process);

        return [
            'restarted' => true,
            'agent' => $agent->name,
            'new_process_id' => $newProcess->id,
            'status' => $newProcess->status,
        ];
    }

    protected function stopAgent(array $params): array
    {
        $agent = Agent::findOrFail($params['agent_id']);
        $process = AgentProcess::where('agent_id', $agent->id)
            ->whereIn('status', ['running', 'sleeping'])
            ->latest()
            ->first();

        if (! $process) {
            return ['error' => "No active process found for agent '{$agent->name}'"];
        }

        $manager = app(DaemonProcessManager::class);
        $manager->stop($process);

        return [
            'stopped' => true,
            'agent' => $agent->name,
        ];
    }

    protected function toggleAgent(array $params): array
    {
        $project = Project::findOrFail($params['project_id']);
        $agent = Agent::findOrFail($params['agent_id']);

        ProjectAgent::updateOrCreate(
            ['project_id' => $project->id, 'agent_id' => $agent->id],
            ['is_enabled' => $params['enabled']],
        );

        return [
            'toggled' => true,
            'agent' => $agent->name,
            'project' => $project->name,
            'enabled' => $params['enabled'],
        ];
    }

    // ── Skill Management ────────────────────────────────────────────

    protected function listSkills(array $params): array
    {
        $project = Project::findOrFail($params['project_id']);
        $skills = $project->skills()
            ->select('id', 'slug', 'name', 'description', 'model', 'project_id')
            ->with('tags:id,name')
            ->orderBy('name')
            ->get();

        return [
            'project' => $project->name,
            'skills' => $skills->map(fn (Skill $s) => [
                'id' => $s->id,
                'slug' => $s->slug,
                'name' => $s->name,
                'description' => $s->description,
                'model' => $s->model,
                'tags' => $s->tags->pluck('name')->toArray(),
            ])->toArray(),
            'count' => $skills->count(),
        ];
    }

    protected function searchSkills(array $params): array
    {
        $query = Skill::with('tags:id,name', 'project:id,name')
            ->select('id', 'slug', 'name', 'description', 'model', 'project_id');

        $q = $params['query'];
        $connection = DB::getDriverName();

        if (in_array($connection, ['mysql', 'mariadb'])) {
            $query->whereRaw('MATCH(name, description, body) AGAINST(? IN BOOLEAN MODE)', [$q]);
        } else {
            $query->where(function ($qb) use ($q) {
                $qb->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('body', 'like', "%{$q}%");
            });
        }

        if (! empty($params['tags'])) {
            $tagNames = explode(',', $params['tags']);
            $query->whereHas('tags', fn ($qb) => $qb->whereIn('name', $tagNames));
        }

        $skills = $query->limit(20)->get();

        return [
            'results' => $skills->map(fn (Skill $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'description' => $s->description,
                'model' => $s->model,
                'project' => $s->project?->name,
                'tags' => $s->tags->pluck('name')->toArray(),
            ])->toArray(),
            'count' => $skills->count(),
        ];
    }

    protected function createSkill(array $params, User $user): array
    {
        $project = Project::findOrFail($params['project_id']);

        $slug = \Illuminate\Support\Str::slug($params['name']);
        $existing = $project->skills()->where('slug', $slug)->exists();
        if ($existing) {
            $slug .= '-' . time();
        }

        $skill = $project->skills()->create([
            'name' => $params['name'],
            'slug' => $slug,
            'description' => $params['description'] ?? null,
            'body' => $params['body'],
            'model' => $params['model'] ?? null,
        ]);

        return [
            'created' => true,
            'skill' => [
                'id' => $skill->id,
                'slug' => $skill->slug,
                'name' => $skill->name,
                'project' => $project->name,
            ],
        ];
    }

    protected function runSkillTest(array $params): array
    {
        $skill = Skill::findOrFail($params['skill_id']);
        $model = $skill->model ?: AppSetting::get('default_model', 'claude-sonnet-4-6');
        $maxTokens = $skill->max_tokens ?: 1024;
        $systemPrompt = $this->compositionService->resolve($skill);

        $provider = $this->providerFactory->make($model);
        $result = $provider->chat($systemPrompt, [
            ['role' => 'user', 'content' => $params['user_message']],
        ], $model, $maxTokens);

        $text = collect($result['content'])
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        return [
            'skill' => $skill->name,
            'model' => $model,
            'response' => $text,
            'usage' => $result['usage'] ?? null,
        ];
    }

    // ── Execution ───────────────────────────────────────────────────

    protected function startExecution(array $params, User $user): array
    {
        $project = Project::findOrFail($params['project_id']);
        $agent = Agent::findOrFail($params['agent_id']);

        $run = ExecutionRun::create([
            'project_id' => $project->id,
            'agent_id' => $agent->id,
            'trigger_type' => 'control_plane',
            'status' => 'pending',
            'input' => ['message' => $params['input']],
            'created_by' => $user->id,
            'model_used' => $agent->model ?? 'claude-sonnet-4-6',
        ]);

        return [
            'started' => true,
            'run' => [
                'id' => $run->id,
                'uuid' => $run->uuid,
                'agent' => $agent->name,
                'project' => $project->name,
                'status' => $run->status,
            ],
        ];
    }

    protected function listRecentRuns(array $params): array
    {
        $query = ExecutionRun::with('agent:id,name,slug', 'project:id,name')
            ->orderByDesc('created_at');

        if (! empty($params['project_id'])) {
            $query->where('project_id', $params['project_id']);
        }
        if (! empty($params['agent_id'])) {
            $query->where('agent_id', $params['agent_id']);
        }

        $limit = min($params['limit'] ?? 10, 50);
        $runs = $query->limit($limit)->get();

        return [
            'runs' => $runs->map(fn (ExecutionRun $r) => [
                'id' => $r->id,
                'agent' => $r->agent?->name,
                'project' => $r->project?->name,
                'status' => $r->status,
                'trigger' => $r->trigger_type,
                'tokens' => $r->total_tokens,
                'duration_ms' => $r->total_duration_ms,
                'started_at' => $r->started_at?->toIso8601String(),
                'completed_at' => $r->completed_at?->toIso8601String(),
                'error' => $r->status === 'failed' ? $r->error : null,
            ])->toArray(),
            'count' => $runs->count(),
        ];
    }

    protected function listFailures(array $params): array
    {
        $query = ExecutionRun::with('agent:id,name,slug', 'project:id,name')
            ->where('status', 'failed')
            ->orderByDesc('created_at');

        if (! empty($params['project_id'])) {
            $query->where('project_id', $params['project_id']);
        }

        $limit = min($params['limit'] ?? 10, 50);
        $runs = $query->limit($limit)->get();

        return [
            'failures' => $runs->map(fn (ExecutionRun $r) => [
                'id' => $r->id,
                'agent' => $r->agent?->name,
                'project' => $r->project?->name,
                'error' => $r->error,
                'model' => $r->model_used,
                'started_at' => $r->started_at?->toIso8601String(),
                'completed_at' => $r->completed_at?->toIso8601String(),
            ])->toArray(),
            'count' => $runs->count(),
        ];
    }

    protected function cancelRun(array $params): array
    {
        $run = ExecutionRun::findOrFail($params['run_id']);

        if (in_array($run->status, ['completed', 'failed', 'cancelled'])) {
            return ['error' => "Run {$run->id} is already {$run->status} and cannot be cancelled"];
        }

        $run->update([
            'status' => 'cancelled',
            'completed_at' => now(),
            'error' => 'Cancelled via control plane',
        ]);

        return [
            'cancelled' => true,
            'run_id' => $run->id,
        ];
    }

    // ── System ──────────────────────────────────────────────────────

    protected function viewDiagnostics(): array
    {
        $checks = [];

        // Database
        try {
            DB::select('SELECT 1');
            $checks['database'] = ['status' => 'ok', 'driver' => DB::getDriverName()];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        // Storage
        $projectsDisk = config('filesystems.disks.projects.root', storage_path('app/projects'));
        $checks['storage'] = [
            'projects_path' => $projectsDisk,
            'writable' => is_writable($projectsDisk),
        ];

        // Queue
        $checks['queue'] = [
            'driver' => config('queue.default'),
        ];

        // PHP
        $checks['php'] = [
            'version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
        ];

        return ['diagnostics' => $checks];
    }

    protected function providerHealth(): array
    {
        $providers = [];

        // Anthropic
        $anthropicKey = AppSetting::get('anthropic_api_key')
            ?: config('services.anthropic.api_key')
            ?: env('ANTHROPIC_API_KEY');
        $providers['anthropic'] = [
            'configured' => ! empty($anthropicKey),
            'models' => ['claude-opus-4-6', 'claude-sonnet-4-6', 'claude-haiku-4-5-20251001'],
        ];

        // OpenAI
        $openaiKey = AppSetting::get('openai_api_key') ?: env('OPENAI_API_KEY');
        $providers['openai'] = [
            'configured' => ! empty($openaiKey),
            'models' => ['gpt-5.4', 'gpt-5-mini', 'o3'],
        ];

        // Gemini
        $geminiKey = AppSetting::get('gemini_api_key') ?: env('GEMINI_API_KEY');
        $providers['gemini'] = [
            'configured' => ! empty($geminiKey),
            'models' => ['gemini-3.1-pro', 'gemini-3-flash'],
        ];

        // Ollama
        $ollamaReachable = false;
        try {
            $baseUrl = rtrim(AppSetting::get('ollama_url') ?: env('OLLAMA_URL', 'http://localhost:11434'), '/');
            $ch = curl_init("{$baseUrl}/api/tags");
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2, CURLOPT_CONNECTTIMEOUT => 1]);
            curl_exec($ch);
            $ollamaReachable = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
            curl_close($ch);
        } catch (\Throwable) {
        }
        $providers['ollama'] = ['configured' => $ollamaReachable, 'reachable' => $ollamaReachable];

        return ['providers' => $providers];
    }

    protected function fleetStatus(): array
    {
        return [
            'projects' => Project::count(),
            'agents' => Agent::count(),
            'skills' => Skill::count(),
            'active_processes' => AgentProcess::whereIn('status', ['running', 'sleeping'])->count(),
            'recent_runs' => ExecutionRun::where('created_at', '>=', now()->subDay())->count(),
            'failed_runs_24h' => ExecutionRun::where('status', 'failed')
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'total_tokens_24h' => (int) ExecutionRun::where('created_at', '>=', now()->subDay())
                ->sum('total_tokens'),
        ];
    }

    // ── Project ─────────────────────────────────────────────────────

    protected function listProjects(): array
    {
        $projects = Project::withCount(['skills', 'projectAgents'])
            ->orderBy('name')
            ->get();

        return [
            'projects' => $projects->map(fn (Project $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'path' => $p->path,
                'skills_count' => $p->skills_count,
                'agents_count' => $p->project_agents_count,
            ])->toArray(),
            'count' => $projects->count(),
        ];
    }

    protected function switchProject(array $params, ?ControlPlaneSession $session): array
    {
        $project = Project::findOrFail($params['project_id']);

        if ($session) {
            $context = $session->context ?? [];
            $context['project_id'] = $project->id;
            $context['project_name'] = $project->name;
            $session->update(['context' => $context]);
        }

        return [
            'switched' => true,
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'path' => $project->path,
            ],
        ];
    }

    protected function viewGraph(array $params): array
    {
        $project = Project::findOrFail($params['project_id']);

        $skills = $project->skills()
            ->select('id', 'slug', 'name', 'includes')
            ->get();

        $agents = Agent::whereIn('id', function ($q) use ($project) {
            $q->select('agent_id')
                ->from('project_agent')
                ->where('project_id', $project->id)
                ->where('is_enabled', true);
        })->select('id', 'name', 'slug', 'role')->get();

        $agentSkills = DB::table('agent_skill')
            ->where('project_id', $project->id)
            ->get()
            ->groupBy('agent_id')
            ->map(fn ($rows) => $rows->pluck('skill_id')->toArray());

        $nodes = [];
        $edges = [];

        foreach ($agents as $agent) {
            $nodes[] = ['id' => "agent:{$agent->id}", 'type' => 'agent', 'label' => $agent->name, 'role' => $agent->role];
            $skillIds = $agentSkills->get($agent->id, []);
            foreach ($skillIds as $skillId) {
                $edges[] = ['from' => "agent:{$agent->id}", 'to' => "skill:{$skillId}"];
            }
        }

        foreach ($skills as $skill) {
            $nodes[] = ['id' => "skill:{$skill->id}", 'type' => 'skill', 'label' => $skill->name];
            foreach ($skill->includes ?? [] as $include) {
                $target = $skills->firstWhere('slug', $include);
                if ($target) {
                    $edges[] = ['from' => "skill:{$skill->id}", 'to' => "skill:{$target->id}", 'type' => 'includes'];
                }
            }
        }

        return [
            'project' => $project->name,
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }
}
