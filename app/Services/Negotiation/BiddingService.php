<?php

namespace App\Services\Negotiation;

use App\Models\Agent;
use App\Models\AgentCapability;
use App\Models\AgentTask;
use App\Models\NegotiationLog;
use App\Models\TaskBid;
use App\Services\Routing\CapabilityTracker;
use App\Services\Routing\SmartTaskRouter;
use Illuminate\Support\Collection;

class BiddingService
{
    public function __construct(
        protected SmartTaskRouter $router,
        protected CapabilityTracker $capabilityTracker,
    ) {}

    /**
     * Open bidding on a task: find eligible agents, score them, and generate bids.
     */
    public function openBidding(AgentTask $task, int $projectId): Collection
    {
        // Get all enabled agents for the project
        $agents = Agent::whereHas('projects', fn ($q) => $q->where('projects.id', $projectId)->where('project_agent.is_enabled', true))
            ->get();

        if ($agents->isEmpty()) {
            return collect();
        }

        $bids = collect();
        $expiresAt = now()->addMinutes(5);

        // Infer task type using the same logic as the router
        $taskText = strtolower(($task->title ?? '') . ' ' . ($task->description ?? ''));
        $taskType = $this->inferTaskType($taskText);

        foreach ($agents as $agent) {
            // Ensure capabilities are inferred for this agent
            $capabilities = $this->capabilityTracker->getCapabilities($agent->id, $projectId);

            if ($capabilities->isEmpty()) {
                $capabilities = $this->capabilityTracker->inferCapabilities($agent, $projectId);
            }

            // Find the best matching capability for this task
            $capability = AgentCapability::where('agent_id', $agent->id)
                ->where('project_id', $projectId)
                ->forCapability($taskType)
                ->first();

            // If no specific capability, use general or skip if the agent has no relevant skills
            if (! $capability) {
                $capability = AgentCapability::where('agent_id', $agent->id)
                    ->where('project_id', $projectId)
                    ->forCapability('general')
                    ->first();
            }

            // Score the agent for this task
            $score = $this->router->scoreAgent($agent, $task, $capability);

            $proficiency = $capability?->proficiency ? (float) $capability->proficiency : 0.5;
            $estimatedDuration = $capability?->avg_duration_ms ?? 30000;
            $estimatedCost = $capability?->avg_cost_microcents ?? 1000;
            $confidence = min(0.99, max(0.10, $proficiency));

            $reasoning = sprintf(
                '%s scored %.2f for %s task. Proficiency: %.2f, Avg duration: %dms, Success rate: %.2f.',
                $agent->name,
                $score,
                $taskType,
                $proficiency,
                $estimatedDuration,
                $capability?->success_rate ? (float) $capability->success_rate : 0.5,
            );

            $bid = TaskBid::create([
                'task_id' => $task->id,
                'agent_id' => $agent->id,
                'project_id' => $projectId,
                'bid_score' => round($score, 2),
                'estimated_duration_ms' => $estimatedDuration,
                'estimated_cost_microcents' => $estimatedCost,
                'confidence' => round($confidence, 2),
                'reasoning' => $reasoning,
                'status' => 'pending',
                'expires_at' => $expiresAt,
            ]);

            // Log the bid
            NegotiationLog::create([
                'task_id' => $task->id,
                'agent_id' => $agent->id,
                'action' => 'bid',
                'details' => [
                    'bid_id' => $bid->id,
                    'score' => round($score, 2),
                    'task_type' => $taskType,
                    'confidence' => round($confidence, 2),
                ],
            ]);

            $bids->push($bid);
        }

        return $bids;
    }

    /**
     * Accept a bid: mark it as accepted, reject all others for the same task, assign the task.
     */
    public function acceptBid(TaskBid $bid): AgentTask
    {
        // Accept the winning bid
        $bid->update(['status' => 'accepted']);

        // Reject all other pending bids for this task
        TaskBid::where('task_id', $bid->task_id)
            ->where('id', '!=', $bid->id)
            ->pending()
            ->update(['status' => 'rejected']);

        // Assign the task to the winning agent
        $task = $bid->task;
        $task->update([
            'agent_id' => $bid->agent_id,
            'status' => 'pending',
        ]);

        // Log acceptance
        NegotiationLog::create([
            'task_id' => $bid->task_id,
            'agent_id' => $bid->agent_id,
            'action' => 'accept',
            'details' => [
                'bid_id' => $bid->id,
                'bid_score' => (float) $bid->bid_score,
            ],
        ]);

        // Log rejections
        TaskBid::where('task_id', $bid->task_id)
            ->where('id', '!=', $bid->id)
            ->where('status', 'rejected')
            ->get()
            ->each(function (TaskBid $rejectedBid) {
                NegotiationLog::create([
                    'task_id' => $rejectedBid->task_id,
                    'agent_id' => $rejectedBid->agent_id,
                    'action' => 'reject',
                    'details' => [
                        'bid_id' => $rejectedBid->id,
                        'reason' => 'Another bid was accepted',
                    ],
                ]);
            });

        return $task->fresh();
    }

    /**
     * Expire all pending bids that are past their expires_at timestamp.
     */
    public function expireBids(): int
    {
        $expiredBids = TaskBid::expired()->get();

        $count = $expiredBids->count();

        foreach ($expiredBids as $bid) {
            $bid->update(['status' => 'expired']);
        }

        return $count;
    }

    /**
     * Infer the task type from text using keyword matching.
     */
    private function inferTaskType(string $text): string
    {
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
