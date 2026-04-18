<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\ComposeShareLink;
use App\Models\Project;
use App\Services\AgentComposeService;
use App\Services\PromptLinter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ComposeShareController extends Controller
{
    protected const DEFAULT_EXPIRY_DAYS = 7;

    public function __construct(
        protected AgentComposeService $composeService,
        protected PromptLinter $linter,
    ) {}

    public function store(Request $request, Project $project, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'model' => 'nullable|string|max:100',
            'depth' => 'nullable|string|in:index,full,deep',
            'expires_in_days' => 'nullable|integer|min:1|max:90',
            'is_snapshot' => 'nullable|boolean',
        ]);

        $depth = $validated['depth'] ?? 'full';
        $modelOverride = $validated['model'] ?? null;
        $isSnapshot = $validated['is_snapshot'] ?? true;
        $expiresInDays = $validated['expires_in_days'] ?? self::DEFAULT_EXPIRY_DAYS;

        $composed = $this->composeService->compose($project, $agent, $depth, $modelOverride);

        $secretIssues = array_values(array_filter(
            $this->linter->lint($composed['content']),
            fn ($issue) => $issue['rule'] === 'secret_in_prompt',
        ));

        if (! empty($secretIssues)) {
            return response()->json([
                'error' => 'Refusing to create share link: composed output contains potential secrets.',
                'secrets' => $secretIssues,
            ], 422);
        }

        $payload = $isSnapshot ? $this->sanitizePayload($composed) : null;

        $link = ComposeShareLink::create([
            'project_id' => $project->id,
            'agent_id' => $agent->id,
            'model' => $modelOverride,
            'depth' => $depth,
            'created_by' => Auth::id(),
            'expires_at' => now()->addDays($expiresInDays),
            'is_snapshot' => $isSnapshot,
            'snapshot_payload' => $payload,
        ]);

        return response()->json([
            'data' => [
                'uuid' => $link->uuid,
                'url' => $this->shareUrl($link),
                'expires_at' => $link->expires_at->toIso8601String(),
                'is_snapshot' => $link->is_snapshot,
            ],
        ], 201);
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $link = ComposeShareLink::where('uuid', $uuid)->firstOrFail();

        if ($link->created_by !== Auth::id()) {
            abort(403, 'Only the creator can delete this share link.');
        }

        $link->delete();

        return response()->json(['message' => 'Share link deleted']);
    }

    public function show(string $uuid): JsonResponse
    {
        $link = ComposeShareLink::where('uuid', $uuid)->first();

        if (! $link) {
            abort(404, 'Share link not found.');
        }

        if ($link->isExpired()) {
            abort(410, 'Share link has expired.');
        }

        if ($link->is_snapshot && $link->snapshot_payload) {
            $payload = $link->snapshot_payload;
        } else {
            $project = $link->project;
            $agent = $link->agent;
            $payload = $this->sanitizePayload(
                $this->composeService->compose($project, $agent, $link->depth, $link->model)
            );
        }

        $link->increment('access_count');
        $link->update(['last_accessed_at' => now()]);

        return response()->json([
            'data' => array_merge($payload, [
                'uuid' => $link->uuid,
                'expires_at' => $link->expires_at?->toIso8601String(),
                'is_snapshot' => $link->is_snapshot,
            ]),
        ]);
    }

    protected function shareUrl(ComposeShareLink $link): string
    {
        return url("/share/compose/{$link->uuid}");
    }

    /**
     * Strip anything with a `secret_*` prefix out of any attached variable maps.
     * Right now the compose payload doesn't include raw variable maps, but this
     * belt-and-suspenders helper protects against future shape changes leaking them.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function sanitizePayload(array $payload): array
    {
        if (isset($payload['skill_variables']) && is_array($payload['skill_variables'])) {
            $payload['skill_variables'] = array_filter(
                $payload['skill_variables'],
                fn ($_value, $key) => ! str_starts_with((string) $key, 'secret_'),
                ARRAY_FILTER_USE_BOTH,
            );
        }

        return $payload;
    }
}
