<?php

namespace App\Http\Controllers;

use App\Models\SkillUpdateProposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SkillProposalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = Auth::id();

        $query = SkillUpdateProposal::query()
            ->with(['skill:id,slug,name', 'agent:id,slug,name,owner_user_id'])
            ->pending()
            ->whereHas('agent', fn ($q) => $q->where('owner_user_id', $userId));

        if ($skillId = $request->query('skill_id')) {
            $query->where('skill_id', $skillId);
        }

        return response()->json([
            'data' => $query->orderByDesc('created_at')->limit(100)->get(),
        ]);
    }

    public function show(SkillUpdateProposal $proposal): JsonResponse
    {
        return response()->json(['data' => $proposal->load(['skill', 'agent'])]);
    }

    public function accept(SkillUpdateProposal $proposal): JsonResponse
    {
        if ($proposal->status !== SkillUpdateProposal::STATUS_DRAFT) {
            return response()->json(['error' => 'Proposal already resolved.'], 422);
        }

        if (! $proposal->skill_id) {
            return response()->json([
                'error' => 'New-skill proposals require a target skill; please attach via skill_id first.',
            ], 422);
        }

        $skill = $proposal->skill;
        if (! $skill) {
            return response()->json(['error' => 'Skill no longer exists.'], 410);
        }

        $nextVersion = ($skill->versions()->max('version_number') ?? 0) + 1;
        $newBody = $proposal->proposed_body ?? $skill->body;

        $version = $skill->versions()->create([
            'version_number' => $nextVersion,
            'frontmatter' => array_merge(
                $proposal->proposed_frontmatter ?? [],
                [
                    'id' => $skill->slug,
                    'name' => $skill->name,
                ],
            ),
            'body' => $newBody,
            'tuned_for_model' => $skill->tuned_for_model,
            'note' => "Accepted proposal #{$proposal->id}: {$proposal->title}",
            'saved_at' => now(),
        ]);

        $skill->update(['body' => $newBody]);

        $proposal->update([
            'status' => SkillUpdateProposal::STATUS_ACCEPTED,
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'proposal' => $proposal->fresh(),
                'new_version_id' => $version->id,
                'new_version_number' => $version->version_number,
            ],
        ]);
    }

    public function reject(Request $request, SkillUpdateProposal $proposal): JsonResponse
    {
        if ($proposal->status !== SkillUpdateProposal::STATUS_DRAFT) {
            return response()->json(['error' => 'Proposal already resolved.'], 422);
        }

        $validated = $request->validate([
            'suppress_days' => 'nullable|integer|min:1|max:365',
        ]);

        $proposal->update([
            'status' => SkillUpdateProposal::STATUS_REJECTED,
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
            'suppress_until' => now()->addDays($validated['suppress_days'] ?? 30),
        ]);

        return response()->json(['data' => $proposal->fresh()]);
    }
}
