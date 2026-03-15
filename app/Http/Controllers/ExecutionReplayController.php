<?php

namespace App\Http\Controllers;

use App\Models\ExecutionReplay;
use App\Models\ExecutionReplayStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExecutionReplayController extends Controller
{
    /**
     * GET /api/executions
     *
     * List execution replays (paginated, filterable).
     */
    public function index(Request $request): JsonResponse
    {
        $query = ExecutionReplay::with('agent')->withCount('steps');

        if ($request->has('project_id')) {
            $query->where('project_id', $request->query('project_id'));
        }

        if ($request->has('agent_id')) {
            $query->where('agent_id', $request->query('agent_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        $replays = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($replays);
    }

    /**
     * GET /api/executions/{executionReplay}
     *
     * Full execution replay with all steps.
     */
    public function show(ExecutionReplay $executionReplay): JsonResponse
    {
        $executionReplay->load(['steps', 'agent']);

        return response()->json(['data' => $executionReplay]);
    }

    /**
     * GET /api/executions/{executionReplay}/steps
     *
     * Individual steps of an execution replay.
     */
    public function steps(ExecutionReplay $executionReplay): JsonResponse
    {
        $steps = $executionReplay->steps()->orderBy('step_number')->get();

        return response()->json(['data' => $steps]);
    }

    /**
     * GET /api/executions/{executionReplay}/diff/{otherReplay}
     *
     * Compare two execution replays side-by-side.
     */
    public function diff(ExecutionReplay $executionReplay, ExecutionReplay $otherReplay): JsonResponse
    {
        $leftSteps = $executionReplay->steps()->orderBy('step_number')->get();
        $rightSteps = $otherReplay->steps()->orderBy('step_number')->get();

        // Index steps by step_number for alignment
        $leftIndexed = $leftSteps->keyBy('step_number');
        $rightIndexed = $rightSteps->keyBy('step_number');

        $allStepNumbers = $leftIndexed->keys()->merge($rightIndexed->keys())->unique()->sort()->values();

        $alignedLeft = [];
        $alignedRight = [];

        foreach ($allStepNumbers as $stepNumber) {
            $alignedLeft[] = $leftIndexed->get($stepNumber);
            $alignedRight[] = $rightIndexed->get($stepNumber);
        }

        $summary = [
            'tokens_diff' => ($otherReplay->total_tokens ?? 0) - ($executionReplay->total_tokens ?? 0),
            'cost_diff' => ($otherReplay->total_cost_microcents ?? 0) - ($executionReplay->total_cost_microcents ?? 0),
            'duration_diff' => ($otherReplay->total_duration_ms ?? 0) - ($executionReplay->total_duration_ms ?? 0),
            'steps_diff' => ($otherReplay->total_steps ?? 0) - ($executionReplay->total_steps ?? 0),
        ];

        return response()->json([
            'data' => [
                'left' => $alignedLeft,
                'right' => $alignedRight,
                'summary' => $summary,
            ],
        ]);
    }
}
