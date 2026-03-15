<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Models\SkillTestCase;
use App\Services\SkillRegressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillRegressionController extends Controller
{
    public function __construct(
        private SkillRegressionService $regressionService,
    ) {}

    /**
     * GET /api/skills/{skill}/test-cases
     */
    public function index(Skill $skill): JsonResponse
    {
        $testCases = $skill->testCases()->orderBy('name')->get();

        return response()->json($testCases);
    }

    /**
     * POST /api/skills/{skill}/test-cases
     */
    public function store(Skill $skill, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'input' => 'required|string',
            'expected_output' => 'nullable|string',
            'assertion_type' => 'nullable|string|in:contains,equals,regex,not_contains',
            'pass_threshold' => 'nullable|numeric|between:0,1',
        ]);

        $testCase = $skill->testCases()->create($validated);

        return response()->json($testCase, 201);
    }

    /**
     * PUT /api/skill-test-cases/{testCase}
     */
    public function update(SkillTestCase $testCase, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'input' => 'sometimes|string',
            'expected_output' => 'nullable|string',
            'assertion_type' => 'nullable|string|in:contains,equals,regex,not_contains',
            'pass_threshold' => 'nullable|numeric|between:0,1',
        ]);

        $testCase->update($validated);

        return response()->json($testCase);
    }

    /**
     * DELETE /api/skill-test-cases/{testCase}
     */
    public function destroy(SkillTestCase $testCase): JsonResponse
    {
        $testCase->delete();

        return response()->json(['success' => true]);
    }

    /**
     * POST /api/skills/{skill}/test-cases/run-all
     */
    public function runAll(Skill $skill, Request $request): JsonResponse
    {
        $outputs = $request->input('outputs', []);

        $results = $this->regressionService->runAllForSkill($skill, $outputs);

        return response()->json($results);
    }
}
