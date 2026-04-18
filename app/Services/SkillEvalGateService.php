<?php

namespace App\Services;

use App\Jobs\RunEvalSuiteJob;
use App\Models\Skill;
use App\Models\SkillEvalGate;
use App\Models\SkillEvalRun;
use App\Models\SkillEvalSuite;
use App\Models\SkillVersion;

class SkillEvalGateService
{
    /**
     * Decide whether a save should trigger eval runs, and if so enqueue them.
     * Returns the decision payload for the SPA to render a banner/modal.
     *
     * @return array{enqueued_run_ids: array<int, int>, baseline_info: array<int, array<string, mixed>>, est_duration_seconds: int, reason: string}
     */
    public function evaluateSkillSave(Skill $skill, SkillVersion $newVersion): array
    {
        $gate = $skill->evalGate;
        $reason = 'no_gate';

        if (! $gate || ! $gate->enabled || ! $gate->auto_run_on_save) {
            return [
                'enqueued_run_ids' => [],
                'baseline_info' => [],
                'est_duration_seconds' => 0,
                'reason' => $gate ? 'disabled' : 'no_gate',
            ];
        }

        $suiteIds = $gate->required_suite_ids ?? [];
        if (empty($suiteIds)) {
            return [
                'enqueued_run_ids' => [],
                'baseline_info' => [],
                'est_duration_seconds' => 0,
                'reason' => 'no_required_suites',
            ];
        }

        $suites = SkillEvalSuite::whereIn('id', $suiteIds)
            ->where('skill_id', $skill->id)
            ->with('prompts')
            ->get();

        $model = $skill->tuned_for_model ?? $skill->model;
        if (! $model) {
            return [
                'enqueued_run_ids' => [],
                'baseline_info' => [],
                'est_duration_seconds' => 0,
                'reason' => 'no_target_model',
            ];
        }

        $enqueued = [];
        $baselineInfo = [];
        $estSeconds = 0;

        foreach ($suites as $suite) {
            $baseline = $this->findBaselineFor($skill, $suite, $model);

            $run = $suite->runs()->create([
                'model' => $model,
                'mode' => 'with_skill',
                'status' => 'pending',
                'skill_version_id' => $newVersion->id,
                'baseline_run_id' => $baseline?->id,
            ]);

            RunEvalSuiteJob::dispatch($run->id);
            $enqueued[] = $run->id;

            $baselineInfo[] = [
                'suite_id' => $suite->id,
                'suite_name' => $suite->name,
                'run_id' => $run->id,
                'baseline_run_id' => $baseline?->id,
                'baseline_score' => $baseline?->overall_score,
            ];

            $estSeconds += max(5, $suite->prompts->count() * 6);
        }

        return [
            'enqueued_run_ids' => $enqueued,
            'baseline_info' => $baselineInfo,
            'est_duration_seconds' => $estSeconds,
            'reason' => 'dispatched',
        ];
    }

    /**
     * Baseline = most recent completed run for (suite, model), regardless
     * of the version it ran against. Uses last-known-good as the anchor
     * rather than "prior version" so revalidating the same version twice
     * doesn't accidentally zero out the delta.
     */
    public function findBaselineFor(Skill $skill, SkillEvalSuite $suite, string $model): ?SkillEvalRun
    {
        return SkillEvalRun::query()
            ->where('eval_suite_id', $suite->id)
            ->where('model', $model)
            ->where('status', 'completed')
            ->orderByDesc('completed_at')
            ->first();
    }

    /**
     * Compute per-prompt + overall deltas between a current run and a baseline.
     *
     * @return array{overall_delta: float, per_prompt: array<int, array{prompt_id: int|null, current: int, baseline: int|null, delta: int|null}>}
     */
    public function computeDelta(SkillEvalRun $current, SkillEvalRun $baseline): array
    {
        $currentScore = (float) ($current->overall_score ?? 0);
        $baselineScore = (float) ($baseline->overall_score ?? 0);

        $baselineByPrompt = [];
        foreach (($baseline->results ?? []) as $result) {
            $pid = $result['prompt_id'] ?? null;
            if ($pid !== null) {
                $baselineByPrompt[$pid] = (int) ($result['score'] ?? 0);
            }
        }

        $perPrompt = [];
        foreach (($current->results ?? []) as $result) {
            $pid = $result['prompt_id'] ?? null;
            $currScore = (int) ($result['score'] ?? 0);
            $baseScore = $pid !== null ? ($baselineByPrompt[$pid] ?? null) : null;
            $perPrompt[] = [
                'prompt_id' => $pid,
                'current' => $currScore,
                'baseline' => $baseScore,
                'delta' => $baseScore !== null ? $currScore - $baseScore : null,
            ];
        }

        return [
            'overall_delta' => round($currentScore - $baselineScore, 2),
            'per_prompt' => $perPrompt,
        ];
    }

    /**
     * Can this skill be synced? If the gate is configured to block sync,
     * the most recent run for each required suite must be either completed
     * with delta ≥ threshold, or not yet run (allow-by-default for first sync).
     */
    public function canSync(Skill $skill): bool
    {
        $gate = $skill->evalGate;
        if (! $gate || ! $gate->enabled || ! $gate->block_sync) {
            return true;
        }

        $suiteIds = $gate->required_suite_ids ?? [];
        if (empty($suiteIds)) {
            return true;
        }

        $threshold = (float) $gate->fail_threshold_delta;
        $model = $skill->tuned_for_model ?? $skill->model;
        if (! $model) {
            return true;
        }

        foreach ($suiteIds as $suiteId) {
            $suite = SkillEvalSuite::find($suiteId);
            if (! $suite) continue;

            $latest = SkillEvalRun::where('eval_suite_id', $suiteId)
                ->where('model', $model)
                ->whereIn('status', ['completed', 'failed'])
                ->orderByDesc('completed_at')
                ->first();

            if (! $latest || $latest->status === 'failed') {
                return false;
            }

            if ($latest->delta_score !== null && (float) $latest->delta_score < $threshold) {
                return false;
            }
        }

        return true;
    }
}
