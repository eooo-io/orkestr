<?php

namespace App\Services;

use App\Models\AgentAuditLog;
use App\Models\Skill;
use App\Models\SkillAnalytic;

class ReportExportService
{
    /**
     * Export skill inventory as array data (for CSV).
     */
    public function exportSkillInventory(?int $projectId = null): array
    {
        $query = Skill::query()->with(['project', 'tags', 'owner']);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        return $query->get()->map(fn (Skill $skill) => [
            'id' => $skill->id,
            'name' => $skill->name,
            'slug' => $skill->slug,
            'project' => $skill->project?->name,
            'model' => $skill->model,
            'owner' => $skill->owner?->name,
            'tags' => $skill->tags->pluck('name')->implode(', '),
            'created_at' => $skill->created_at?->toIso8601String(),
            'updated_at' => $skill->updated_at?->toIso8601String(),
        ])->toArray();
    }

    /**
     * Export usage report from skill analytics.
     */
    public function exportUsageReport(?string $from = null, ?string $to = null, ?int $organizationId = null): array
    {
        $query = SkillAnalytic::query()->with('skill');

        if ($from) {
            $query->where('date', '>=', $from);
        }
        if ($to) {
            $query->where('date', '<=', $to);
        }
        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        return $query->orderBy('date')->get()->map(fn (SkillAnalytic $a) => [
            'date' => $a->date->toDateString(),
            'skill_id' => $a->skill_id,
            'skill_name' => $a->skill?->name,
            'test_runs' => $a->test_runs,
            'pass_count' => $a->pass_count,
            'fail_count' => $a->fail_count,
            'avg_tokens' => $a->avg_tokens,
            'avg_cost_microcents' => $a->avg_cost_microcents,
            'avg_latency_ms' => $a->avg_latency_ms,
        ])->toArray();
    }

    /**
     * Export audit log data.
     */
    public function exportAuditLog(?string $from = null, ?string $to = null): array
    {
        $query = AgentAuditLog::query()->orderByDesc('created_at')->limit(10000);

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query->get()->map(fn ($log) => [
            'uuid' => $log->uuid,
            'event' => $log->event,
            'severity' => $log->severity,
            'description' => $log->description,
            'user_email' => $log->user_email,
            'agent_id' => $log->agent_id,
            'project_id' => $log->project_id,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at?->toIso8601String(),
        ])->toArray();
    }
}
