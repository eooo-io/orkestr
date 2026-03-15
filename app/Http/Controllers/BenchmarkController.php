<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Services\CrossModelBenchmarkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BenchmarkController extends Controller
{
    public function __construct(
        private CrossModelBenchmarkService $benchmarkService,
    ) {}

    /**
     * POST /api/skills/{skill}/benchmark
     */
    public function benchmark(Skill $skill, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'models' => 'required|array|min:1',
            'models.*' => 'required|string',
        ]);

        $results = $this->benchmarkService->benchmarkSkill($skill, $validated['models']);

        return response()->json($results);
    }
}
