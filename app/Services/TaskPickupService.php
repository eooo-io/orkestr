<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\Notification;
use App\Models\Project;

class TaskPickupService
{
    /**
     * Pick up the next pending task for an agent in a project.
     * Returns the highest-priority, oldest-created pending task.
     */
    public function pickupNext(Agent $agent, Project $project): ?AgentTask
    {
        return AgentTask::where('agent_id', $agent->id)
            ->where('project_id', $project->id)
            ->where('status', 'pending')
            ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->orderBy('created_at', 'asc')
            ->first();
    }

    /**
     * Mark a task as running and link it to an execution.
     */
    public function markRunning(AgentTask $task, int $executionId): void
    {
        $task->update([
            'status' => 'running',
            'execution_id' => $executionId,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark a task as completed and store output.
     */
    public function markCompleted(AgentTask $task, array $output): void
    {
        $task->update([
            'status' => 'completed',
            'output_data' => $output,
            'completed_at' => now(),
        ]);

        $this->createNotification($task, 'task_completed');
    }

    /**
     * Mark a task as failed and store the error.
     */
    public function markFailed(AgentTask $task, string $error): void
    {
        $task->update([
            'status' => 'failed',
            'output_data' => ['error' => $error],
            'completed_at' => now(),
        ]);

        $this->createNotification($task, 'task_failed');
    }

    /**
     * Create a notification for a completed or failed task.
     */
    private function createNotification(AgentTask $task, string $type): void
    {
        $agentName = $task->agent?->displayName() ?? 'Unknown Agent';
        $duration = $task->started_at && $task->completed_at
            ? $task->completed_at->diffInSeconds($task->started_at)
            : null;

        $isCompleted = $type === 'task_completed';
        $title = $isCompleted
            ? "Task completed: {$task->title}"
            : "Task failed: {$task->title}";

        $body = $isCompleted
            ? "Agent {$agentName} completed the task" . ($duration ? " in {$duration}s" : '') . '.'
            : "Agent {$agentName} failed to complete the task.";

        // Notify the user who created the task
        if ($task->assigned_by_user_id) {
            Notification::create([
                'user_id' => $task->assigned_by_user_id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => [
                    'task_id' => $task->id,
                    'agent_id' => $task->agent_id,
                    'agent_name' => $agentName,
                    'execution_id' => $task->execution_id,
                    'duration_seconds' => $duration,
                ],
                'created_at' => now(),
            ]);
        }
    }
}
