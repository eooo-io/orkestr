<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentTask;

class OrchestratorRoutingService
{
    /**
     * Keyword → agent slug mapping for deterministic routing.
     */
    private const ROUTING_MAP = [
        'security-engineer' => [
            'security', 'vulnerability', 'audit', 'authentication', 'authorization',
            'encrypt', 'token', 'password', 'credential', 'xss', 'csrf', 'injection',
            'ssl', 'tls', 'firewall', 'penetration', 'threat',
        ],
        'qa-engineer' => [
            'test', 'testing', 'qa', 'quality', 'regression', 'unit test', 'integration test',
            'e2e', 'assertion', 'coverage', 'bug', 'defect', 'verify', 'validate',
        ],
        'documentation-engineer' => [
            'document', 'documentation', 'readme', 'guide', 'tutorial', 'api doc',
            'comment', 'jsdoc', 'phpdoc', 'changelog', 'wiki', 'write-up',
        ],
        'performance-optimizer' => [
            'performance', 'optimize', 'speed', 'latency', 'cache', 'benchmark',
            'memory', 'cpu', 'profil', 'bottleneck', 'slow', 'fast', 'throughput',
        ],
        'devops-engineer' => [
            'deploy', 'infrastructure', 'docker', 'kubernetes', 'ci/cd', 'pipeline',
            'server', 'nginx', 'terraform', 'ansible', 'monitoring', 'log',
            'container', 'devops', 'cloud', 'aws', 'gcp', 'azure',
        ],
        'research-analyst' => [
            'research', 'analyze', 'investigate', 'compare', 'evaluate', 'study',
            'survey', 'report', 'findings', 'data analysis', 'trend', 'insight',
        ],
        'ux-designer' => [
            'ux', 'ui', 'design', 'interface', 'usability', 'accessibility',
            'layout', 'wireframe', 'prototype', 'user experience', 'responsive',
        ],
    ];

    /**
     * Decompose a task with no assigned agent into subtasks routed to specialists.
     */
    public function decompose(AgentTask $task): void
    {
        $description = strtolower(($task->title ?? '') . ' ' . ($task->description ?? ''));

        // Find the best matching agent role
        $matchedSlug = $this->matchAgentSlug($description);

        if ($matchedSlug) {
            $agent = Agent::where('slug', $matchedSlug)->first();

            if ($agent) {
                // Create a single subtask assigned to the matched agent
                AgentTask::create([
                    'project_id' => $task->project_id,
                    'agent_id' => $agent->id,
                    'parent_task_id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'priority' => $task->priority,
                    'status' => 'assigned',
                    'input_data' => $task->input_data,
                    'assigned_by_agent_id' => $this->getOrchestratorId(),
                ]);

                $task->update(['status' => 'assigned']);

                return;
            }
        }

        // Fallback: assign to the first available enabled agent in the project
        $fallbackAgent = $task->project
            ->agents()
            ->wherePivot('is_enabled', true)
            ->first();

        if ($fallbackAgent) {
            AgentTask::create([
                'project_id' => $task->project_id,
                'agent_id' => $fallbackAgent->id,
                'parent_task_id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'priority' => $task->priority,
                'status' => 'assigned',
                'input_data' => $task->input_data,
                'assigned_by_agent_id' => $this->getOrchestratorId(),
            ]);

            $task->update(['status' => 'assigned']);

            return;
        }

        // No agent available — leave as pending
        $task->update(['status' => 'pending']);
    }

    /**
     * Match the best agent slug based on keyword frequency in the description.
     */
    private function matchAgentSlug(string $text): ?string
    {
        $bestSlug = null;
        $bestScore = 0;

        foreach (self::ROUTING_MAP as $slug => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    $score++;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestSlug = $slug;
            }
        }

        return $bestScore > 0 ? $bestSlug : null;
    }

    /**
     * Get the orchestrator agent ID (if it exists).
     */
    private function getOrchestratorId(): ?int
    {
        return Agent::where('slug', 'orchestrator')->value('id');
    }
}
