<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\ExecutionRun;
use Illuminate\Support\Facades\DB;

/**
 * "Who should I ask about X?" — ranks agents by how well their attached skills
 * and recent execution inputs match a natural-language question. Intentionally
 * simple: term-frequency overlap, no vector search yet.
 */
class AgentRoutingService
{
    public const MAX_RESULTS = 10;
    public const RUN_SAMPLE_SIZE = 30;

    /**
     * @return array<int, array{agent_id: int, name: string, score: float, reasoning: string, reputation_score: float|null, owner: array|null}>
     */
    public function rank(string $question, ?int $projectId = null): array
    {
        $questionTokens = $this->tokenize($question);
        if (empty($questionTokens)) {
            return [];
        }

        $agentsQuery = Agent::query()->with('owner');
        if ($projectId !== null) {
            $agentIds = DB::table('project_agent')
                ->where('project_id', $projectId)
                ->where('is_enabled', true)
                ->pluck('agent_id');
            $agentsQuery->whereIn('id', $agentIds);
        }

        $agents = $agentsQuery->get();
        $rankings = [];

        foreach ($agents as $agent) {
            $skillOverlap = $this->scoreSkillOverlap($agent->id, $questionTokens);
            $runOverlap = $this->scoreRunOverlap($agent->id, $questionTokens);

            $score = ($skillOverlap * 0.7) + ($runOverlap * 0.3);
            if ($score <= 0) continue;

            $reasoning = $this->buildReasoning($skillOverlap, $runOverlap);

            $rankings[] = [
                'agent_id' => $agent->id,
                'slug' => $agent->slug,
                'name' => $agent->name,
                'icon' => $agent->icon,
                'role' => $agent->role,
                'score' => round($score, 3),
                'reasoning' => $reasoning,
                'reputation_score' => $agent->reputation_score !== null
                    ? (float) $agent->reputation_score
                    : null,
                'owner' => $agent->owner ? [
                    'id' => $agent->owner->id,
                    'name' => $agent->owner->name,
                ] : null,
            ];
        }

        usort($rankings, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($rankings, 0, self::MAX_RESULTS);
    }

    /**
     * Fraction of question tokens that appear in any attached skill's name/description/summary.
     */
    protected function scoreSkillOverlap(int $agentId, array $tokens): float
    {
        $rows = DB::table('agent_skill')
            ->join('skills', 'skills.id', '=', 'agent_skill.skill_id')
            ->where('agent_skill.agent_id', $agentId)
            ->select('skills.name', 'skills.description', 'skills.summary')
            ->get();

        if ($rows->isEmpty()) return 0.0;

        $combined = mb_strtolower($rows->map(fn ($r) => trim(
            ($r->name ?? '') . ' ' . ($r->description ?? '') . ' ' . ($r->summary ?? '')
        ))->implode(' '));

        $hits = 0;
        foreach ($tokens as $t) {
            if (str_contains($combined, $t)) $hits++;
        }

        return $hits / count($tokens);
    }

    /**
     * Fraction of question tokens that appear in recent run inputs.
     */
    protected function scoreRunOverlap(int $agentId, array $tokens): float
    {
        $inputs = ExecutionRun::where('agent_id', $agentId)
            ->orderByDesc('created_at')
            ->limit(self::RUN_SAMPLE_SIZE)
            ->pluck('input');

        if ($inputs->isEmpty()) return 0.0;

        $combined = '';
        foreach ($inputs as $raw) {
            $input = is_string($raw) ? json_decode($raw, true) : $raw;
            if (! is_array($input)) continue;
            $text = ($input['message'] ?? '') . ' ' . ($input['goal'] ?? '');
            $combined .= ' ' . mb_strtolower($text);
        }

        if (trim($combined) === '') return 0.0;

        $hits = 0;
        foreach ($tokens as $t) {
            if (str_contains($combined, $t)) $hits++;
        }

        return $hits / count($tokens);
    }

    /**
     * @return array<int, string>
     */
    protected function tokenize(string $text): array
    {
        $stopwords = [
            'the', 'a', 'an', 'and', 'or', 'but', 'of', 'to', 'in', 'on',
            'at', 'for', 'with', 'by', 'from', 'is', 'are', 'was', 'were',
            'who', 'what', 'when', 'where', 'why', 'how', 'should', 'ask',
            'about', 'do', 'does', 'did', 'can', 'could', 'would',
        ];

        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $tokens,
            fn (string $t) => mb_strlen($t) >= 3 && ! in_array($t, $stopwords, true),
        )));
    }

    protected function buildReasoning(float $skillOverlap, float $runOverlap): string
    {
        $parts = [];
        if ($skillOverlap > 0) {
            $parts[] = sprintf('%d%% skill overlap', (int) round($skillOverlap * 100));
        }
        if ($runOverlap > 0) {
            $parts[] = sprintf('%d%% past-run overlap', (int) round($runOverlap * 100));
        }

        return $parts ? implode(', ', $parts) : 'no strong signal';
    }
}
