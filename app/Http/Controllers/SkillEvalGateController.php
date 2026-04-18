<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Models\SkillEvalGate;
use App\Models\SkillEvalRun;
use App\Services\SkillEvalGateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillEvalGateController extends Controller
{
    public function __construct(
        protected SkillEvalGateService $gateService,
    ) {}

    public function show(Skill $skill): JsonResponse
    {
        $gate = $skill->evalGate ?? new SkillEvalGate([
            'skill_id' => $skill->id,
            'enabled' => false,
            'required_suite_ids' => [],
            'fail_threshold_delta' => -5.00,
            'auto_run_on_save' => false,
            'block_sync' => false,
        ]);

        return response()->json(['data' => $gate]);
    }

    public function update(Request $request, Skill $skill): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'boolean',
            'required_suite_ids' => 'nullable|array',
            'required_suite_ids.*' => 'integer|exists:skill_eval_suites,id',
            'fail_threshold_delta' => 'nullable|numeric|between:-100,100',
            'auto_run_on_save' => 'boolean',
            'block_sync' => 'boolean',
        ]);

        $gate = $skill->evalGate()->firstOrNew();
        $gate->fill($validated);
        $gate->skill_id = $skill->id;
        $gate->save();

        return response()->json(['data' => $gate->fresh()]);
    }

    public function runNow(Skill $skill): JsonResponse
    {
        $version = $skill->versions()->orderByDesc('version_number')->first();

        if (! $version) {
            return response()->json([
                'error' => 'No version snapshot exists — save the skill first.',
            ], 422);
        }

        $decision = $this->gateService->evaluateSkillSave($skill, $version);

        return response()->json(['data' => $decision]);
    }

    public function status(Skill $skill): JsonResponse
    {
        $gate = $skill->evalGate;

        if (! $gate || empty($gate->required_suite_ids ?? [])) {
            return response()->json([
                'data' => [
                    'enabled' => (bool) ($gate?->enabled),
                    'runs' => [],
                    'pending_count' => 0,
                    'can_sync' => $this->gateService->canSync($skill),
                ],
            ]);
        }

        $model = $skill->tuned_for_model ?? $skill->model;
        $suiteIds = $gate->required_suite_ids ?? [];
        $runs = [];
        $pendingCount = 0;

        foreach ($suiteIds as $suiteId) {
            $latest = SkillEvalRun::where('eval_suite_id', $suiteId)
                ->when($model, fn ($q) => $q->where('model', $model))
                ->orderByDesc('created_at')
                ->first();

            if (! $latest) continue;

            if ($latest->status === 'pending' || $latest->status === 'running') {
                $pendingCount++;
            }

            $baseline = $latest->baseline_run_id
                ? SkillEvalRun::find($latest->baseline_run_id)
                : null;

            $delta = $baseline ? $this->gateService->computeDelta($latest, $baseline) : null;

            $runs[] = [
                'suite_id' => $suiteId,
                'run_id' => $latest->id,
                'status' => $latest->status,
                'overall_score' => $latest->overall_score,
                'delta' => $delta,
            ];
        }

        return response()->json([
            'data' => [
                'enabled' => (bool) $gate->enabled,
                'runs' => $runs,
                'pending_count' => $pendingCount,
                'can_sync' => $this->gateService->canSync($skill),
            ],
        ]);
    }
}
