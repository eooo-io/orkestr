<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Models\SkillEvalPrompt;
use App\Models\SkillEvalRun;
use App\Models\SkillEvalSuite;
use App\Jobs\RunEvalSuiteJob;
use App\Services\PromptLinter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvalSuiteController extends Controller
{
    /**
     * List eval suites for a skill.
     */
    public function index(Skill $skill): JsonResponse
    {
        $suites = $skill->evalSuites()
            ->withCount(['prompts', 'runs'])
            ->with(['runs' => fn ($q) => $q->latest()->limit(1)])
            ->get();

        return response()->json(['data' => $suites]);
    }

    /**
     * Show a single eval suite with prompts.
     */
    public function show(SkillEvalSuite $evalSuite): JsonResponse
    {
        $evalSuite->load(['prompts', 'runs']);

        return response()->json(['data' => $evalSuite]);
    }

    /**
     * Create eval suite.
     */
    public function store(Request $request, Skill $skill): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $suite = $skill->evalSuites()->create($validated);

        return response()->json(['data' => $suite], 201);
    }

    /**
     * Update eval suite.
     */
    public function update(Request $request, SkillEvalSuite $evalSuite): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $evalSuite->update($validated);

        return response()->json(['data' => $evalSuite]);
    }

    /**
     * Delete eval suite.
     */
    public function destroy(SkillEvalSuite $evalSuite): JsonResponse
    {
        $evalSuite->delete();

        return response()->json(['message' => 'Eval suite deleted']);
    }

    /**
     * Manage prompts within a suite.
     */
    public function storePrompt(Request $request, SkillEvalSuite $evalSuite): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'required|string|max:10000',
            'expected_behavior' => 'nullable|string|max:5000',
            'grading_criteria' => 'nullable|array',
        ]);

        $maxOrder = $evalSuite->prompts()->max('sort_order') ?? 0;
        $validated['sort_order'] = $maxOrder + 1;

        $prompt = $evalSuite->prompts()->create($validated);

        return response()->json(['data' => $prompt], 201);
    }

    public function updatePrompt(Request $request, SkillEvalPrompt $evalPrompt): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'sometimes|required|string|max:10000',
            'expected_behavior' => 'nullable|string|max:5000',
            'grading_criteria' => 'nullable|array',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $evalPrompt->update($validated);

        return response()->json(['data' => $evalPrompt]);
    }

    public function destroyPrompt(SkillEvalPrompt $evalPrompt): JsonResponse
    {
        $evalPrompt->delete();

        return response()->json(['message' => 'Prompt deleted']);
    }

    /**
     * Trigger an eval run.
     */
    public function run(Request $request, SkillEvalSuite $evalSuite): JsonResponse
    {
        $validated = $request->validate([
            'model' => 'required|string|max:100',
            'mode' => 'required|string|in:with_skill,without_skill,ab_test',
        ]);

        $skill = $evalSuite->skill;
        $currentVersion = $skill
            ? $skill->versions()->orderByDesc('version_number')->first()
            : null;

        $run = $evalSuite->runs()->create([
            'model' => $validated['model'],
            'mode' => $validated['mode'],
            'status' => 'pending',
            'skill_version_id' => $currentVersion?->id,
        ]);

        RunEvalSuiteJob::dispatch($run->id);

        return response()->json(['data' => $run->fresh()], 201);
    }

    /**
     * List runs for a suite.
     */
    public function runs(SkillEvalSuite $evalSuite): JsonResponse
    {
        return response()->json([
            'data' => $evalSuite->runs()->latest()->get(),
        ]);
    }

    /**
     * Show a single run.
     */
    public function showRun(SkillEvalRun $evalRun): JsonResponse
    {
        return response()->json(['data' => $evalRun]);
    }

    /**
     * Score a skill's description quality.
     */
    public function scoreDescription(Skill $skill): JsonResponse
    {
        $description = $skill->description ?? '';
        $summary = $skill->summary ?? '';

        $issues = [];
        $score = 100;

        if (strlen($description) < 20) {
            $issues[] = 'Description is too short — may not trigger when needed.';
            $score -= 30;
        }
        if (strlen($description) > 500) {
            $issues[] = 'Description is too long — may waste context tokens.';
            $score -= 10;
        }

        foreach (PromptLinter::VAGUE_DESCRIPTION_WORDS as $word) {
            if (stripos($description, $word) !== false) {
                $issues[] = "Description contains vague word: \"{$word}\".";
                $score -= 10;
            }
        }

        $hasVerb = false;
        foreach (PromptLinter::DESCRIPTION_ACTION_VERBS as $verb) {
            if (stripos($description, $verb) !== false) {
                $hasVerb = true;
                break;
            }
        }
        if (! $hasVerb) {
            $issues[] = 'Description lacks an actionable verb — agents may not know when to use this skill.';
            $score -= 15;
        }

        if (empty($summary)) {
            $issues[] = 'No summary set — tier-1 progressive disclosure will fall back to description.';
            $score -= 5;
        }

        $score = max(0, min(100, $score));

        return response()->json([
            'data' => [
                'score' => $score,
                'issues' => $issues,
                'name' => $skill->name,
                'description' => $description,
                'summary' => $summary,
            ],
        ]);
    }

}
