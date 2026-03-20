<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Models\SkillGotcha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GotchaController extends Controller
{
    public function index(Skill $skill, Request $request): JsonResponse
    {
        $query = $skill->gotchas()->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")->latest();

        if ($request->query('active_only') === 'true') {
            $query->active();
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request, Skill $skill): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'severity' => 'sometimes|string|in:critical,warning,info',
            'source' => 'sometimes|string|in:manual,test_failure,execution,review',
            'source_reference_id' => 'nullable|integer',
        ]);

        $gotcha = $skill->gotchas()->create($validated);

        return response()->json(['data' => $gotcha], 201);
    }

    public function update(Request $request, SkillGotcha $skillGotcha): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string|max:5000',
            'severity' => 'sometimes|string|in:critical,warning,info',
        ]);

        $skillGotcha->update($validated);

        return response()->json(['data' => $skillGotcha]);
    }

    public function destroy(SkillGotcha $skillGotcha): JsonResponse
    {
        $skillGotcha->delete();

        return response()->json(['message' => 'Gotcha deleted']);
    }

    public function resolve(SkillGotcha $skillGotcha): JsonResponse
    {
        $skillGotcha->update(['resolved_at' => now()]);

        return response()->json(['data' => $skillGotcha]);
    }

    public function reopen(SkillGotcha $skillGotcha): JsonResponse
    {
        $skillGotcha->update(['resolved_at' => null]);

        return response()->json(['data' => $skillGotcha]);
    }
}
