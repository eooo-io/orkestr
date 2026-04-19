<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentMemory;
use App\Models\SkillUpdateProposal;
use Illuminate\Support\Facades\DB;

/**
 * When an agent gets told the same thing repeatedly, Orkestr proposes
 * encoding that feedback into a durable skill update. Compound engineering:
 * runtime learning → declarative config.
 *
 * The v1 heuristic is intentionally simple: look at long-term / working
 * memories whose content normalizes to the same canonical form, and open
 * a proposal once the frequency crosses a threshold.
 */
class PatternExtractionService
{
    public const FREQUENCY_THRESHOLD = 3;
    public const WINDOW_DAYS = 30;

    /**
     * Scan one agent's memories and open proposals for repeating patterns.
     * Returns the number of proposals created / updated.
     */
    public function extractForAgent(Agent $agent): int
    {
        $cutoff = now()->subDays(self::WINDOW_DAYS);

        $memories = AgentMemory::query()
            ->where('agent_id', $agent->id)
            ->whereIn('type', ['long_term', 'working'])
            ->where('created_at', '>=', $cutoff)
            ->get();

        if ($memories->isEmpty()) return 0;

        // Group by canonical form.
        $patterns = [];
        foreach ($memories as $memory) {
            $text = $this->extractText($memory->content);
            if ($text === '') continue;

            $canonical = $this->canonicalize($text);
            if ($canonical === '') continue;

            $patterns[$canonical] ??= ['text' => $text, 'memory_ids' => []];
            $patterns[$canonical]['memory_ids'][] = $memory->id;
        }

        $created = 0;
        foreach ($patterns as $key => $info) {
            if (count($info['memory_ids']) < self::FREQUENCY_THRESHOLD) continue;

            $proposal = SkillUpdateProposal::where('agent_id', $agent->id)
                ->where('pattern_key', $key)
                ->first();

            // Don't reopen suppressed rejections
            if ($proposal && $proposal->status === SkillUpdateProposal::STATUS_REJECTED) {
                if ($proposal->suppress_until && $proposal->suppress_until->isFuture()) continue;
            }

            $attrs = [
                'title' => 'Encode repeated feedback: "' . mb_strimwidth($info['text'], 0, 80, '…') . '"',
                'rationale' => sprintf(
                    'Detected in %d memories over the last %d days. Encoding as a skill update would save repeating the reminder.',
                    count($info['memory_ids']),
                    self::WINDOW_DAYS,
                ),
                'proposed_body' => $info['text'],
                'evidence_memory_ids' => $info['memory_ids'],
                'status' => SkillUpdateProposal::STATUS_DRAFT,
            ];

            if ($proposal) {
                $proposal->update($attrs);
            } else {
                SkillUpdateProposal::create($attrs + [
                    'agent_id' => $agent->id,
                    'pattern_key' => $key,
                ]);
                $created++;
            }
        }

        return $created;
    }

    /**
     * Extract plain text from whatever shape the memory content is in.
     *
     * @param  mixed  $content
     */
    protected function extractText($content): string
    {
        if (is_string($content)) return trim($content);
        if (! is_array($content)) return '';

        foreach (['text', 'value', 'body', 'instruction', 'preference'] as $key) {
            if (isset($content[$key]) && is_string($content[$key])) {
                return trim($content[$key]);
            }
        }

        $collected = [];
        array_walk_recursive($content, function ($v) use (&$collected) {
            if (is_string($v) && strlen($v) > 3) $collected[] = $v;
        });

        return trim(implode(' ', $collected));
    }

    /**
     * Collapse a piece of feedback to a canonical form so "always use pnpm" and
     * "Always use pnpm!" hit the same bucket. Keeps just lowercase alphanum +
     * spaces and drops stopwords.
     */
    protected function canonicalize(string $text): string
    {
        $stopwords = [
            'the', 'a', 'an', 'and', 'or', 'but', 'of', 'to', 'in', 'on',
            'please', 'remember', 'note', 'always', 'never', 'make', 'sure',
            'use', 'using', 'be', 'is', 'are', 'was', 'were', 'do', 'does',
        ];

        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_values(array_filter(
            $tokens,
            fn (string $t) => mb_strlen($t) >= 3 && ! in_array($t, $stopwords, true),
        ));

        return implode(' ', $tokens);
    }
}
