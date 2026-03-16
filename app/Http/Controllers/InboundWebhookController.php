<?php

namespace App\Http\Controllers;

use App\Jobs\ProjectScanJob;
use App\Jobs\RunScheduledAgentJob;
use App\Models\AgentSchedule;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InboundWebhookController extends Controller
{
    public function github(Request $request, Project $project): JsonResponse
    {
        // Validate GitHub webhook signature if a secret is configured
        $configuredSecret = $project->webhooks()
            ->where('event', 'github.push')
            ->value('secret');

        if ($configuredSecret) {
            $signature = $request->header('X-Hub-Signature-256');

            if (! $signature) {
                return response()->json(['message' => 'Missing signature'], 403);
            }

            $expectedSignature = 'sha256=' . hash_hmac('sha256', $request->getContent(), $configuredSecret);

            if (! hash_equals($expectedSignature, $signature)) {
                return response()->json(['message' => 'Invalid signature'], 403);
            }
        }

        // Only trigger scan for push events (default GitHub webhook event)
        $event = $request->header('X-GitHub-Event', 'push');

        $executionIds = [];

        if ($event === 'push') {
            ProjectScanJob::dispatch($project);

            // Also trigger any agents with webhook schedules for this project
            $executionIds = $this->dispatchWebhookTriggeredAgents(
                $project,
                $request->all(),
                "github.{$event}"
            );

            return response()->json([
                'message' => 'Scan queued',
                'executions_triggered' => count($executionIds),
                'execution_ids' => $executionIds,
            ]);
        }

        return response()->json(['message' => 'Event ignored']);
    }

    /**
     * POST /api/webhooks/inbound/{projectId}
     *
     * Generic inbound webhook that triggers agent execution for
     * agents with webhook-type schedules in the project.
     */
    public function generic(Request $request, Project $project): JsonResponse
    {
        $executionIds = $this->dispatchWebhookTriggeredAgents(
            $project,
            $request->all(),
            $request->header('X-Webhook-Event', 'generic')
        );

        if (empty($executionIds)) {
            return response()->json([
                'message' => 'No webhook-triggered agents found for this project.',
                'executions_triggered' => 0,
            ]);
        }

        return response()->json([
            'message' => 'Accepted',
            'executions_triggered' => count($executionIds),
            'execution_ids' => $executionIds,
        ], 202);
    }

    /**
     * Find all webhook-type schedules for a project and dispatch them.
     *
     * @return array<string> Execution schedule IDs that were dispatched
     */
    private function dispatchWebhookTriggeredAgents(Project $project, array $payload, string $source): array
    {
        $webhookSchedules = AgentSchedule::where('project_id', $project->id)
            ->webhook()
            ->enabled()
            ->get();

        $dispatched = [];

        foreach ($webhookSchedules as $schedule) {
            $webhookPayload = array_merge($payload, [
                '_webhook_source' => $source,
            ]);

            RunScheduledAgentJob::dispatch($schedule, $webhookPayload, 'webhook');
            $dispatched[] = $schedule->uuid;
        }

        return $dispatched;
    }
}
