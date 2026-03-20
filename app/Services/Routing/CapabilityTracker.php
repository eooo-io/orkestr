<?php

namespace App\Services\Routing;

use App\Models\Agent;
use App\Models\AgentCapability;
use App\Models\ExecutionRun;
use Illuminate\Support\Collection;

class CapabilityTracker
{
    /**
     * Exponential moving average smoothing factor.
     * Higher = more weight on recent data. Range: 0.0 - 1.0.
     */
    private const EMA_ALPHA = 0.3;

    /**
     * Keyword → capability mapping for inference.
     */
    private const SKILL_CAPABILITY_MAP = [
        'code_review' => ['review', 'code-review', 'pull-request', 'pr-review', 'lint'],
        'security_audit' => ['security', 'vulnerability', 'audit', 'penetration', 'owasp'],
        'testing' => ['test', 'qa', 'regression', 'unit-test', 'e2e', 'coverage', 'assert'],
        'documentation' => ['document', 'readme', 'guide', 'tutorial', 'api-doc', 'changelog'],
        'performance' => ['performance', 'optimize', 'benchmark', 'profil', 'cache', 'speed'],
        'deployment' => ['deploy', 'docker', 'ci-cd', 'pipeline', 'kubernetes', 'infrastructure'],
        'research' => ['research', 'analyze', 'investigate', 'compare', 'evaluate', 'survey'],
        'design' => ['ux', 'ui', 'design', 'wireframe', 'prototype', 'accessibility'],
        'bug_fix' => ['bug', 'fix', 'debug', 'error', 'patch', 'hotfix'],
        'feature' => ['feature', 'implement', 'build', 'create', 'scaffold'],
        'refactor' => ['refactor', 'clean', 'restructure', 'simplify', 'migrate'],
    ];

    /**
     * Role → capability mapping for inference.
     */
    private const ROLE_CAPABILITY_MAP = [
        'security-engineer' => ['security_audit', 'code_review'],
        'qa-engineer' => ['testing', 'bug_fix'],
        'documentation-engineer' => ['documentation'],
        'performance-optimizer' => ['performance', 'refactor'],
        'devops-engineer' => ['deployment', 'performance'],
        'research-analyst' => ['research', 'documentation'],
        'ux-designer' => ['design'],
        'frontend-developer' => ['feature', 'bug_fix', 'design'],
        'backend-developer' => ['feature', 'bug_fix', 'refactor'],
        'full-stack-developer' => ['feature', 'bug_fix', 'refactor', 'testing'],
        'orchestrator' => ['research', 'feature'],
        'architect' => ['design', 'refactor', 'code_review'],
    ];

    /**
     * Update capability stats after an execution completes.
     * Uses exponential moving average for rolling statistics.
     */
    public function updateFromExecution(ExecutionRun $run): void
    {
        if (! $run->agent_id || ! $run->project_id) {
            return;
        }

        $taskType = $this->inferTaskTypeFromRun($run);
        $isSuccess = $run->isCompleted();
        $duration = $run->total_duration_ms ?? 0;
        $cost = $run->total_cost_microcents ?? 0;

        $capability = AgentCapability::firstOrCreate(
            [
                'agent_id' => $run->agent_id,
                'project_id' => $run->project_id,
                'capability' => $taskType,
            ],
            [
                'proficiency' => 0.50,
                'avg_duration_ms' => $duration,
                'avg_cost_microcents' => $cost,
                'success_rate' => $isSuccess ? 1.0 : 0.0,
                'task_count' => 0,
            ]
        );

        $alpha = self::EMA_ALPHA;

        // Update proficiency: success increases it, failure decreases it
        $proficiencyDelta = $isSuccess ? 0.05 : -0.08;
        $newProficiency = max(0.0, min(0.99, (float) $capability->proficiency + $proficiencyDelta));

        // EMA for duration and cost
        $newDuration = $capability->task_count > 0
            ? (int) ($alpha * $duration + (1 - $alpha) * $capability->avg_duration_ms)
            : $duration;

        $newCost = $capability->task_count > 0
            ? (int) ($alpha * $cost + (1 - $alpha) * $capability->avg_cost_microcents)
            : $cost;

        // EMA for success rate
        $successValue = $isSuccess ? 1.0 : 0.0;
        $newSuccessRate = $capability->task_count > 0
            ? $alpha * $successValue + (1 - $alpha) * (float) $capability->success_rate
            : $successValue;

        $capability->update([
            'proficiency' => round($newProficiency, 2),
            'avg_duration_ms' => $newDuration,
            'avg_cost_microcents' => $newCost,
            'success_rate' => round(max(0.0, min(0.99, $newSuccessRate)), 2),
            'task_count' => $capability->task_count + 1,
            'last_used_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Infer initial capabilities for an agent based on its skills, role, and description.
     */
    public function inferCapabilities(Agent $agent, int $projectId): Collection
    {
        $capabilities = collect();
        $detectedCapabilities = [];

        // 1. Infer from agent role/slug
        $slug = $agent->slug ?? '';
        $role = $agent->role ?? '';
        $roleKey = $slug ?: $role;

        foreach (self::ROLE_CAPABILITY_MAP as $rolePattern => $caps) {
            if (str_contains(strtolower($roleKey), str_replace('-', '', $rolePattern))
                || str_contains(strtolower($roleKey), $rolePattern)) {
                foreach ($caps as $cap) {
                    $detectedCapabilities[$cap] = ($detectedCapabilities[$cap] ?? 0) + 2;
                }
            }
        }

        // 2. Infer from agent description
        $description = strtolower($agent->description ?? '');
        foreach (self::SKILL_CAPABILITY_MAP as $capability => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($description, $keyword)) {
                    $detectedCapabilities[$capability] = ($detectedCapabilities[$capability] ?? 0) + 1;
                }
            }
        }

        // 3. Infer from assigned skills
        $skillNames = $agent->projects()
            ->where('projects.id', $projectId)
            ->first()
            ?->pivot
            ?->custom_instructions ?? '';

        // Also check agent's skills via the agent_skill pivot
        $skills = \DB::table('agent_skill')
            ->join('skills', 'skills.id', '=', 'agent_skill.skill_id')
            ->where('agent_skill.project_id', $projectId)
            ->pluck('skills.name');

        foreach ($skills as $skillName) {
            $lowerName = strtolower($skillName);
            foreach (self::SKILL_CAPABILITY_MAP as $capability => $keywords) {
                foreach ($keywords as $keyword) {
                    if (str_contains($lowerName, $keyword)) {
                        $detectedCapabilities[$capability] = ($detectedCapabilities[$capability] ?? 0) + 1;
                    }
                }
            }
        }

        // 4. Create or update capability records
        foreach ($detectedCapabilities as $capName => $confidence) {
            $proficiency = min(0.80, 0.30 + ($confidence * 0.10));

            $record = AgentCapability::updateOrCreate(
                [
                    'agent_id' => $agent->id,
                    'project_id' => $projectId,
                    'capability' => $capName,
                ],
                [
                    'proficiency' => round($proficiency, 2),
                    'updated_at' => now(),
                ]
            );

            $capabilities->push($record);
        }

        // If no capabilities detected, add a "general" capability
        if ($capabilities->isEmpty()) {
            $record = AgentCapability::updateOrCreate(
                [
                    'agent_id' => $agent->id,
                    'project_id' => $projectId,
                    'capability' => 'general',
                ],
                [
                    'proficiency' => 0.50,
                    'updated_at' => now(),
                ]
            );

            $capabilities->push($record);
        }

        return $capabilities;
    }

    /**
     * Retrieve all capabilities for an agent in a project.
     */
    public function getCapabilities(int $agentId, int $projectId): Collection
    {
        return AgentCapability::where('agent_id', $agentId)
            ->where('project_id', $projectId)
            ->orderByDesc('proficiency')
            ->get();
    }

    /**
     * Infer task type from execution run input.
     */
    private function inferTaskTypeFromRun(ExecutionRun $run): string
    {
        $input = $run->input ?? [];
        $text = strtolower(
            ($input['prompt'] ?? '')
            . ' ' . ($input['title'] ?? '')
            . ' ' . ($input['description'] ?? '')
        );

        if (empty(trim($text))) {
            return 'general';
        }

        $typeMap = [
            'code_review' => ['review', 'code review', 'pull request', 'pr review'],
            'security_audit' => ['security', 'vulnerability', 'audit', 'penetration'],
            'testing' => ['test', 'qa', 'regression', 'unit test', 'e2e'],
            'documentation' => ['document', 'readme', 'guide', 'tutorial'],
            'performance' => ['performance', 'optimize', 'benchmark'],
            'deployment' => ['deploy', 'docker', 'ci/cd', 'pipeline'],
            'research' => ['research', 'analyze', 'investigate'],
            'design' => ['ux', 'ui', 'design', 'wireframe'],
            'bug_fix' => ['bug', 'fix', 'defect', 'error'],
            'feature' => ['feature', 'implement', 'build', 'create'],
            'refactor' => ['refactor', 'clean', 'restructure'],
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
}
