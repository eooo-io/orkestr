<?php

namespace App\Services\Negotiation;

use App\Models\Agent;
use App\Models\AgentCapability;
use App\Models\AgentTask;
use App\Models\ExecutionRun;
use App\Models\NegotiationLog;
use App\Models\TeamFormation;
use App\Services\Routing\SmartTaskRouter;
use Illuminate\Support\Collection;

class TeamFormationService
{
    /**
     * Keyword map for extracting required capabilities from objective text.
     */
    private const OBJECTIVE_CAPABILITY_MAP = [
        'code_review' => ['review', 'code review', 'pull request', 'pr review', 'lint', 'code quality'],
        'security_audit' => ['security', 'vulnerability', 'audit', 'penetration', 'owasp', 'threat'],
        'testing' => ['test', 'qa', 'regression', 'unit test', 'e2e', 'coverage', 'quality assurance'],
        'documentation' => ['document', 'readme', 'guide', 'tutorial', 'api doc', 'changelog'],
        'performance' => ['performance', 'optimize', 'benchmark', 'profiling', 'cache', 'speed', 'latency'],
        'deployment' => ['deploy', 'docker', 'ci/cd', 'pipeline', 'kubernetes', 'infrastructure', 'devops'],
        'research' => ['research', 'analyze', 'investigate', 'compare', 'evaluate', 'survey', 'explore'],
        'design' => ['ux', 'ui', 'design', 'wireframe', 'prototype', 'accessibility', 'interface'],
        'bug_fix' => ['bug', 'fix', 'debug', 'error', 'patch', 'hotfix', 'defect'],
        'feature' => ['feature', 'implement', 'build', 'create', 'scaffold', 'develop', 'new'],
        'refactor' => ['refactor', 'clean', 'restructure', 'simplify', 'migrate', 'modernize'],
    ];

    public function __construct(
        protected SmartTaskRouter $router,
    ) {}

    /**
     * Form a team by analyzing the objective and selecting the best agents.
     */
    public function formTeam(
        int $projectId,
        string $objective,
        string $strategy = 'capability_match',
        ?int $userId = null,
    ): TeamFormation {
        // 1. Extract required capabilities from the objective
        $requiredCapabilities = $this->extractCapabilities($objective);

        // If we couldn't detect any specific capabilities, default to general
        if (empty($requiredCapabilities)) {
            $requiredCapabilities = ['general'];
        }

        // 2. Get all enabled agents for the project
        $agents = Agent::whereHas('projects', fn ($q) => $q->where('projects.id', $projectId)->where('project_agent.is_enabled', true))
            ->get();

        // 3. Score each agent for the required capabilities
        $agentScores = [];

        foreach ($agents as $agent) {
            $totalScore = 0;
            $matchedCapabilities = 0;

            foreach ($requiredCapabilities as $capName) {
                $capability = AgentCapability::where('agent_id', $agent->id)
                    ->where('project_id', $projectId)
                    ->forCapability($capName)
                    ->first();

                if ($capability) {
                    $matchedCapabilities++;
                    $proficiency = (float) $capability->proficiency;
                    $successRate = (float) ($capability->success_rate ?? 0.5);

                    // Strategy-specific scoring
                    $capScore = match ($strategy) {
                        'cost_optimized' => $proficiency * 0.3 + (1000 / max(1, $capability->avg_cost_microcents ?? 1000)) * 0.7,
                        'speed_optimized' => $proficiency * 0.3 + (30000 / max(1, $capability->avg_duration_ms ?? 30000)) * 0.7,
                        default => $proficiency * 0.6 + $successRate * 0.4, // capability_match
                    };

                    $totalScore += $capScore;
                }
            }

            $agentScores[] = [
                'agent' => $agent,
                'score' => $totalScore,
                'matched' => $matchedCapabilities,
                'coverage' => count($requiredCapabilities) > 0
                    ? $matchedCapabilities / count($requiredCapabilities)
                    : 0,
            ];
        }

        // 4. Select agents: pick the best agent for each capability, ensuring coverage
        $selectedAgentIds = $this->selectAgentsForTeam($agentScores, $requiredCapabilities, $projectId);

        // Ensure at least one agent is selected
        if (empty($selectedAgentIds) && $agents->isNotEmpty()) {
            // Fallback: pick the agent with the highest overall score
            usort($agentScores, fn ($a, $b) => $b['score'] <=> $a['score']);
            $selectedAgentIds = [$agentScores[0]['agent']->id];
        }

        // 5. Create the team formation
        $formation = TeamFormation::create([
            'project_id' => $projectId,
            'name' => 'Team: ' . substr($objective, 0, 80),
            'objective' => $objective,
            'formation_strategy' => $strategy,
            'agent_ids' => array_values(array_unique($selectedAgentIds)),
            'status' => 'active',
            'formed_by_user_id' => $userId,
        ]);

        // 6. Log the formation
        foreach ($selectedAgentIds as $agentId) {
            NegotiationLog::create([
                'team_formation_id' => $formation->id,
                'agent_id' => $agentId,
                'action' => 'join_team',
                'details' => [
                    'team_id' => $formation->id,
                    'objective' => $objective,
                    'strategy' => $strategy,
                    'required_capabilities' => $requiredCapabilities,
                ],
            ]);
        }

        // Log the team formation event using the first selected agent
        if (! empty($selectedAgentIds)) {
            NegotiationLog::create([
                'team_formation_id' => $formation->id,
                'agent_id' => $selectedAgentIds[0],
                'action' => 'form_team',
                'details' => [
                    'team_id' => $formation->id,
                    'name' => $formation->name,
                    'strategy' => $strategy,
                    'agent_count' => count($selectedAgentIds),
                    'required_capabilities' => $requiredCapabilities,
                ],
            ]);
        }

        return $formation;
    }

    /**
     * Disband a team formation.
     */
    public function disband(TeamFormation $formation): void
    {
        $formation->update([
            'status' => 'disbanded',
            'disbanded_at' => now(),
        ]);

        // Log leave events for each member
        foreach ($formation->agent_ids ?? [] as $agentId) {
            NegotiationLog::create([
                'team_formation_id' => $formation->id,
                'agent_id' => $agentId,
                'action' => 'leave_team',
                'details' => [
                    'team_id' => $formation->id,
                    'reason' => 'Team disbanded',
                ],
            ]);
        }
    }

    /**
     * Evaluate team performance based on members' execution success rates.
     */
    public function evaluatePerformance(TeamFormation $formation): float
    {
        $agentIds = $formation->agent_ids ?? [];

        if (empty($agentIds)) {
            return 0.0;
        }

        $totalScore = 0;
        $agentCount = 0;

        foreach ($agentIds as $agentId) {
            // Get execution runs for this agent in the team's project
            $runs = ExecutionRun::where('agent_id', $agentId)
                ->where('project_id', $formation->project_id)
                ->whereIn('status', ['completed', 'failed'])
                ->get();

            if ($runs->isEmpty()) {
                $totalScore += 0.5; // Default neutral score for agents with no runs
            } else {
                $successCount = $runs->where('status', 'completed')->count();
                $totalScore += $runs->count() > 0 ? $successCount / $runs->count() : 0.5;
            }

            $agentCount++;
        }

        $performanceScore = $agentCount > 0 ? round($totalScore / $agentCount, 2) : 0.0;

        // Update the formation's performance score
        $formation->update(['performance_score' => $performanceScore]);

        return $performanceScore;
    }

    /**
     * Extract required capabilities from objective text using keyword matching.
     */
    private function extractCapabilities(string $objective): array
    {
        $text = strtolower($objective);
        $detected = [];

        foreach (self::OBJECTIVE_CAPABILITY_MAP as $capability => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    $detected[$capability] = ($detected[$capability] ?? 0) + 1;
                }
            }
        }

        // Sort by match count descending and return the capability names
        arsort($detected);

        return array_keys($detected);
    }

    /**
     * Select agents that provide the best coverage of required capabilities.
     */
    private function selectAgentsForTeam(array $agentScores, array $requiredCapabilities, int $projectId): array
    {
        $selectedIds = [];
        $coveredCapabilities = [];

        // For each required capability, find the best agent
        foreach ($requiredCapabilities as $capName) {
            $bestAgent = null;
            $bestScore = -1;

            foreach ($agentScores as $entry) {
                $agent = $entry['agent'];

                $capability = AgentCapability::where('agent_id', $agent->id)
                    ->where('project_id', $projectId)
                    ->forCapability($capName)
                    ->first();

                if ($capability) {
                    $score = (float) ($capability->proficiency ?? 0.5);
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestAgent = $agent;
                    }
                }
            }

            if ($bestAgent && ! in_array($bestAgent->id, $selectedIds)) {
                $selectedIds[] = $bestAgent->id;
                $coveredCapabilities[] = $capName;
            }
        }

        return $selectedIds;
    }
}
