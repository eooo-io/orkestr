<?php

namespace App\Services\Routing;

use App\Models\Agent;
use App\Models\AgentCapability;
use App\Models\AgentTask;
use App\Models\ExecutionRun;
use App\Models\RoutingDecision;
use App\Models\RoutingRule;

class SmartTaskRouter
{
    public function __construct(
        protected LoadBalancer $loadBalancer,
        protected CapabilityTracker $capabilityTracker,
    ) {}

    /**
     * Route a task to the best agent for the given project.
     */
    public function route(AgentTask $task, int $projectId): ?Agent
    {
        // 1. Find matching routing rules
        $rules = $this->findMatchingRules($task, $projectId);

        // Use the highest-priority matching rule, or a default best_fit strategy
        $rule = $rules->first();
        $strategy = $rule?->target_strategy ?? 'best_fit';

        // 2. Get candidate agents
        $candidates = $this->getCandidateAgents($rule, $projectId);

        if ($candidates->isEmpty()) {
            $this->recordDecision($task, null, $strategy, [], 'No candidate agents available');

            return null;
        }

        // 3. Score each candidate
        $taskType = $this->inferTaskType($task);
        $scoredCandidates = [];

        foreach ($candidates as $agent) {
            $capability = AgentCapability::where('agent_id', $agent->id)
                ->where('project_id', $projectId)
                ->forCapability($taskType)
                ->first();

            $score = $this->scoreAgent($agent, $task, $capability);

            $scoredCandidates[] = [
                'agent_id' => $agent->id,
                'agent_name' => $agent->name,
                'score' => round($score, 4),
                'available' => $this->loadBalancer->isAvailable($agent->id),
                'load_factor' => $this->loadBalancer->getLoadFactor($agent->id),
                'proficiency' => $capability?->proficiency ? (float) $capability->proficiency : 0.5,
                'success_rate' => $capability?->success_rate ? (float) $capability->success_rate : 0.5,
                'avg_duration_ms' => $capability?->avg_duration_ms ?? 0,
                'avg_cost_microcents' => $capability?->avg_cost_microcents ?? 0,
            ];
        }

        // 4. Select winner via strategy
        $winner = $this->selectByStrategy($scoredCandidates, $strategy);

        if (! $winner) {
            $this->recordDecision($task, null, $strategy, $scoredCandidates, 'No available agent matched strategy');

            return null;
        }

        $selectedAgent = Agent::find($winner['agent_id']);

        // 5. Record decision
        $reasoning = sprintf(
            'Selected %s (score: %.4f) via %s strategy. Task type: %s. %d candidates evaluated.',
            $winner['agent_name'],
            $winner['score'],
            $strategy,
            $taskType,
            count($scoredCandidates),
        );

        $slaMet = true;
        if ($rule?->sla_config) {
            $slaCheck = app(SlaMonitor::class)->checkSla($task, $rule);
            $slaMet = $slaCheck['met'];
        }

        $this->recordDecision($task, $selectedAgent, $strategy, $scoredCandidates, $reasoning, $slaMet);

        return $selectedAgent;
    }

    /**
     * Score an agent for a given task based on multiple factors.
     */
    public function scoreAgent(Agent $agent, AgentTask $task, ?AgentCapability $capability): float
    {
        $proficiency = $capability?->proficiency ? (float) $capability->proficiency : 0.5;
        $successRate = $capability?->success_rate ? (float) $capability->success_rate : 0.5;
        $loadFactor = $this->loadBalancer->getLoadFactor($agent->id);
        $availability = max(0, 1.0 - $loadFactor);

        // Speed: normalize duration (lower is better). Baseline 30s = 30000ms.
        $avgDuration = $capability?->avg_duration_ms ?? 30000;
        $speed = $avgDuration > 0 ? min(1.0, 30000 / $avgDuration) : 0.5;

        // Cost: normalize cost (lower is better). Baseline 1000 microcents.
        $avgCost = $capability?->avg_cost_microcents ?? 1000;
        $costEfficiency = $avgCost > 0 ? min(1.0, 1000 / $avgCost) : 0.5;

        // Weighted composite
        return ($proficiency * 0.40)
            + ($availability * 0.25)
            + ($speed * 0.15)
            + ($costEfficiency * 0.10)
            + ($successRate * 0.10);
    }

    /**
     * Apply the chosen strategy to select from scored candidates.
     */
    public function selectByStrategy(array $scoredCandidates, string $strategy): ?array
    {
        // Filter to only available candidates for most strategies
        $available = array_filter($scoredCandidates, fn ($c) => $c['available']);

        // If no available candidates, fall back to all candidates for best_fit
        if (empty($available) && $strategy === 'best_fit') {
            $available = $scoredCandidates;
        }

        if (empty($available)) {
            return null;
        }

        return match ($strategy) {
            'best_fit' => $this->selectBestFit($available),
            'round_robin' => $this->selectRoundRobin($available),
            'least_loaded' => $this->selectLeastLoaded($available),
            'cost_optimized' => $this->selectCostOptimized($available),
            'fastest' => $this->selectFastest($available),
            default => $this->selectBestFit($available),
        };
    }

    /**
     * Dry-run simulation: returns what would happen without creating records.
     */
    public function simulate(string $description, string $taskType, int $projectId, ?string $priority = 'medium'): array
    {
        // Build a fake task for scoring
        $fakeTask = new AgentTask([
            'title' => $description,
            'description' => $description,
            'priority' => $priority,
            'project_id' => $projectId,
            'status' => 'pending',
        ]);

        $rules = $this->findMatchingRules($fakeTask, $projectId);
        $rule = $rules->first();
        $strategy = $rule?->target_strategy ?? 'best_fit';

        $candidates = $this->getCandidateAgents($rule, $projectId);
        $scoredCandidates = [];

        foreach ($candidates as $agent) {
            $capability = AgentCapability::where('agent_id', $agent->id)
                ->where('project_id', $projectId)
                ->forCapability($taskType)
                ->first();

            $score = $this->scoreAgent($agent, $fakeTask, $capability);

            $scoredCandidates[] = [
                'agent_id' => $agent->id,
                'agent_name' => $agent->name,
                'score' => round($score, 4),
                'available' => $this->loadBalancer->isAvailable($agent->id),
                'load_factor' => $this->loadBalancer->getLoadFactor($agent->id),
                'proficiency' => $capability?->proficiency ? (float) $capability->proficiency : 0.5,
                'success_rate' => $capability?->success_rate ? (float) $capability->success_rate : 0.5,
                'avg_duration_ms' => $capability?->avg_duration_ms ?? 0,
                'avg_cost_microcents' => $capability?->avg_cost_microcents ?? 0,
            ];
        }

        $winner = $this->selectByStrategy($scoredCandidates, $strategy);

        return [
            'selected_agent' => $winner,
            'strategy' => $strategy,
            'rule' => $rule ? [
                'id' => $rule->id,
                'name' => $rule->name,
                'conditions' => $rule->conditions,
            ] : null,
            'candidates' => $scoredCandidates,
            'task_type' => $taskType,
            'reasoning' => $winner
                ? sprintf(
                    'Would select %s (score: %.4f) via %s strategy. %d candidates evaluated.',
                    $winner['agent_name'],
                    $winner['score'],
                    $strategy,
                    count($scoredCandidates),
                )
                : 'No suitable agent found.',
        ];
    }

    // --- Private helpers ---

    private function findMatchingRules(AgentTask $task, int $projectId): \Illuminate\Support\Collection
    {
        $rules = RoutingRule::where('project_id', $projectId)
            ->active()
            ->orderByDesc('priority')
            ->get();

        return $rules->filter(function (RoutingRule $rule) use ($task) {
            return $this->matchesConditions($rule->conditions, $task);
        });
    }

    private function matchesConditions(array $conditions, AgentTask $task): bool
    {
        $description = strtolower(($task->title ?? '') . ' ' . ($task->description ?? ''));

        // Match by task_type
        if (! empty($conditions['task_type'])) {
            $taskType = $this->inferTaskType($task);
            if ($taskType !== $conditions['task_type']) {
                return false;
            }
        }

        // Match by priority
        if (! empty($conditions['priority'])) {
            $priorities = (array) $conditions['priority'];
            if (! in_array($task->priority, $priorities)) {
                return false;
            }
        }

        // Match by tags (keywords in description)
        if (! empty($conditions['tags'])) {
            $matchedAny = false;
            foreach ((array) $conditions['tags'] as $tag) {
                if (str_contains($description, strtolower($tag))) {
                    $matchedAny = true;
                    break;
                }
            }
            if (! $matchedAny) {
                return false;
            }
        }

        // Match by keyword patterns
        if (! empty($conditions['keywords'])) {
            $matchedAny = false;
            foreach ((array) $conditions['keywords'] as $keyword) {
                if (str_contains($description, strtolower($keyword))) {
                    $matchedAny = true;
                    break;
                }
            }
            if (! $matchedAny) {
                return false;
            }
        }

        return true;
    }

    private function getCandidateAgents(?RoutingRule $rule, int $projectId): \Illuminate\Support\Collection
    {
        // If rule specifies explicit agents, use those
        if ($rule && ! empty($rule->target_agents)) {
            return Agent::whereIn('id', $rule->target_agents)
                ->whereHas('projects', fn ($q) => $q->where('projects.id', $projectId)->where('project_agent.is_enabled', true))
                ->get();
        }

        // Otherwise, all enabled agents for the project
        return Agent::whereHas('projects', fn ($q) => $q->where('projects.id', $projectId)->where('project_agent.is_enabled', true))
            ->get();
    }

    private function inferTaskType(AgentTask $task): string
    {
        $text = strtolower(($task->title ?? '') . ' ' . ($task->description ?? ''));

        $typeMap = [
            'code_review' => ['review', 'code review', 'pull request', 'pr review', 'merge request'],
            'security_audit' => ['security', 'vulnerability', 'audit', 'penetration', 'xss', 'csrf', 'injection'],
            'testing' => ['test', 'testing', 'qa', 'regression', 'unit test', 'e2e', 'coverage'],
            'documentation' => ['document', 'documentation', 'readme', 'guide', 'tutorial', 'api doc'],
            'performance' => ['performance', 'optimize', 'speed', 'latency', 'benchmark', 'profil'],
            'deployment' => ['deploy', 'infrastructure', 'docker', 'ci/cd', 'pipeline', 'kubernetes'],
            'research' => ['research', 'analyze', 'investigate', 'compare', 'evaluate', 'study'],
            'design' => ['ux', 'ui', 'design', 'interface', 'usability', 'wireframe'],
            'bug_fix' => ['bug', 'fix', 'defect', 'error', 'issue', 'broken', 'crash'],
            'feature' => ['feature', 'implement', 'build', 'create', 'add', 'new'],
            'refactor' => ['refactor', 'clean', 'restructure', 'reorganize', 'simplify'],
        ];

        $bestType = 'general';
        $bestScore = 0;

        foreach ($typeMap as $type => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestType = $type;
            }
        }

        return $bestType;
    }

    private function selectBestFit(array $candidates): ?array
    {
        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $candidates[0] ?? null;
    }

    private function selectRoundRobin(array $candidates): ?array
    {
        if (empty($candidates)) {
            return null;
        }

        $totalTasks = AgentTask::count();
        $index = $totalTasks % count($candidates);

        // Sort by agent_id for stable ordering
        usort($candidates, fn ($a, $b) => $a['agent_id'] <=> $b['agent_id']);

        return $candidates[$index];
    }

    private function selectLeastLoaded(array $candidates): ?array
    {
        usort($candidates, fn ($a, $b) => $a['load_factor'] <=> $b['load_factor']);

        return $candidates[0] ?? null;
    }

    private function selectCostOptimized(array $candidates): ?array
    {
        usort($candidates, fn ($a, $b) => $a['avg_cost_microcents'] <=> $b['avg_cost_microcents']);

        return $candidates[0] ?? null;
    }

    private function selectFastest(array $candidates): ?array
    {
        usort($candidates, function ($a, $b) {
            $aDur = $a['avg_duration_ms'] ?: PHP_INT_MAX;
            $bDur = $b['avg_duration_ms'] ?: PHP_INT_MAX;

            return $aDur <=> $bDur;
        });

        return $candidates[0] ?? null;
    }

    private function recordDecision(
        AgentTask $task,
        ?Agent $agent,
        string $strategy,
        array $candidates,
        string $reasoning,
        bool $slaMet = true,
    ): void {
        RoutingDecision::create([
            'task_id' => $task->id,
            'selected_agent_id' => $agent?->id,
            'strategy_used' => $strategy,
            'candidates' => $candidates,
            'reasoning' => $reasoning,
            'sla_met' => $slaMet,
            'created_at' => now(),
        ]);
    }
}
