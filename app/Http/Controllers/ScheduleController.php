<?php

namespace App\Http\Controllers;

use App\Http\Resources\AgentScheduleResource;
use App\Http\Resources\ExecutionRunResource;
use App\Jobs\RunScheduledAgentJob;
use App\Models\AgentSchedule;
use App\Models\Project;
use Cron\CronExpression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        $schedules = $project->schedules()
            ->with('agent')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => AgentScheduleResource::collection($schedules),
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'agent_id' => 'required|exists:agents,id',
            'trigger_type' => 'required|in:cron,webhook,event',
            'cron_expression' => [
                'required_if:trigger_type,cron',
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if ($value && ! CronExpression::isValidExpression($value)) {
                        $fail('The cron expression is not valid.');
                    }
                },
            ],
            'timezone' => 'sometimes|timezone:all',
            'webhook_secret' => 'nullable|string|max:255',
            'event_name' => 'required_if:trigger_type,event|nullable|string|max:255',
            'event_filters' => 'nullable|array',
            'input_template' => 'nullable|array',
            'execution_config' => 'nullable|array',
            'is_enabled' => 'nullable|boolean',
            'max_retries' => 'nullable|integer|min:0|max:5',
        ]);

        $validated['project_id'] = $project->id;
        $validated['created_by'] = $request->user()?->id;

        $schedule = AgentSchedule::create($validated);

        return response()->json([
            'data' => new AgentScheduleResource($schedule->load('agent')),
        ], 201);
    }

    public function show(AgentSchedule $schedule): JsonResponse
    {
        $schedule->load('agent');

        return response()->json([
            'data' => new AgentScheduleResource($schedule),
        ]);
    }

    public function update(Request $request, AgentSchedule $schedule): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'agent_id' => 'sometimes|required|exists:agents,id',
            'trigger_type' => 'sometimes|required|in:cron,webhook,event',
            'cron_expression' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if ($value && ! CronExpression::isValidExpression($value)) {
                        $fail('The cron expression is not valid.');
                    }
                },
            ],
            'timezone' => 'sometimes|timezone:all',
            'webhook_secret' => 'nullable|string|max:255',
            'event_name' => 'nullable|string|max:255',
            'event_filters' => 'nullable|array',
            'input_template' => 'nullable|array',
            'execution_config' => 'nullable|array',
            'is_enabled' => 'nullable|boolean',
            'max_retries' => 'nullable|integer|min:0|max:5',
        ]);

        $schedule->update($validated);

        // Recompute next_run_at if cron expression or timezone changed
        $triggerType = $validated['trigger_type'] ?? $schedule->trigger_type;
        if ($triggerType === 'cron' && (isset($validated['cron_expression']) || isset($validated['timezone']))) {
            $schedule->update(['next_run_at' => $schedule->computeNextRun()]);
        }

        return response()->json([
            'data' => new AgentScheduleResource($schedule->fresh()->load('agent')),
        ]);
    }

    public function destroy(AgentSchedule $schedule): JsonResponse
    {
        $schedule->delete();

        return response()->json(['message' => 'Schedule deleted']);
    }

    public function toggle(Request $request, AgentSchedule $schedule): JsonResponse
    {
        $schedule->update(['is_enabled' => ! $schedule->is_enabled]);

        // Recompute next_run_at when enabling a cron schedule
        if ($schedule->is_enabled && $schedule->trigger_type === 'cron') {
            $schedule->update(['next_run_at' => $schedule->computeNextRun()]);
        }

        return response()->json([
            'data' => new AgentScheduleResource($schedule->fresh()->load('agent')),
        ]);
    }

    public function trigger(AgentSchedule $schedule): JsonResponse
    {
        RunScheduledAgentJob::dispatch($schedule, [], 'manual');

        return response()->json(['message' => 'Schedule triggered']);
    }

    public function runs(AgentSchedule $schedule): JsonResponse
    {
        $runs = $schedule->executionRuns()
            ->with('agent')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => ExecutionRunResource::collection($runs),
        ]);
    }

    /**
     * Handle inbound webhook triggers (public, no auth).
     */
    public function webhookTrigger(Request $request, string $token): JsonResponse
    {
        $schedule = AgentSchedule::where('webhook_token', $token)->first();

        if (! $schedule) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if (! $schedule->is_enabled) {
            return response()->json(['message' => 'Schedule is disabled'], 422);
        }

        if ($schedule->trigger_type !== 'webhook') {
            return response()->json(['message' => 'Schedule is not a webhook trigger'], 422);
        }

        // Validate HMAC signature if webhook_secret is set
        if ($schedule->webhook_secret) {
            $signature = $request->header('X-Signature-256') ?? $request->header('X-Hub-Signature-256');

            if (! $signature) {
                return response()->json(['message' => 'Missing signature'], 403);
            }

            $expectedSignature = 'sha256=' . hash_hmac('sha256', $request->getContent(), $schedule->webhook_secret);

            if (! hash_equals($expectedSignature, $signature)) {
                return response()->json(['message' => 'Invalid signature'], 403);
            }
        }

        RunScheduledAgentJob::dispatch($schedule, $request->all(), 'webhook');

        return response()->json(['message' => 'Accepted'], 202);
    }
}
