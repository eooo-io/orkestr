<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\ExecutionRun;
use Illuminate\Support\Facades\DB;

/**
 * Compute an agent's reputation score from its run history.
 *
 * The formula is intentionally simple and auditable: stakeholders can read
 * the code and understand exactly why an agent is at 72.5 vs 68.0.
 */
class AgentReputationService
{
    public const WINDOW_DAYS = 30;
    public const MIN_RUNS_FOR_SCORE = 3;

    /**
     * Recompute and persist the reputation for a single agent.
     */
    public function computeFor(Agent $agent): float
    {
        $score = $this->calculate($agent);

        $agent->update([
            'reputation_score' => $score,
            'reputation_last_computed_at' => now(),
        ]);

        return $score;
    }

    /**
     * Pure function — doesn't persist. Useful for tests + preview.
     *
     * Formula:
     *   - start at 50 (neutral)
     *   - + up to 30 pts for success rate on last 30 days of runs
     *   - + up to 15 pts for positive review ratio (if reviews exist)
     *   - − up to 20 pts for guardrail halt rate
     *   - clamp to [0, 100]
     *   - return 0 when no signal (< MIN_RUNS_FOR_SCORE runs)
     */
    public function calculate(Agent $agent): float
    {
        $since = now()->subDays(self::WINDOW_DAYS);
        $runs = ExecutionRun::where('agent_id', $agent->id)
            ->where('created_at', '>=', $since)
            ->select('status')
            ->get();

        if ($runs->count() < self::MIN_RUNS_FOR_SCORE) {
            return 0.0;
        }

        $total = $runs->count();
        $completed = $runs->where('status', 'completed')->count();
        $halted = $runs->where('status', 'halted_guardrail')->count();
        $failed = $runs->where('status', 'failed')->count();

        $successRate = $completed / max(1, $total);
        $haltRate = $halted / max(1, $total);

        $score = 50.0;
        $score += $successRate * 30.0;      // up to +30
        $score -= $haltRate * 20.0;         // up to -20
        $score -= ($failed / max(1, $total)) * 10.0; // up to -10 for plain failures

        $reviewSignal = $this->reviewSignal($agent);
        if ($reviewSignal !== null) {
            $score += $reviewSignal * 15.0; // up to +/-15
        }

        return (float) round(max(0.0, min(100.0, $score)), 2);
    }

    /**
     * Returns a value in [-1, 1] based on review ratio, or null when no reviews.
     * Agents without reviews don't get penalized.
     */
    protected function reviewSignal(Agent $agent): ?float
    {
        // Reviews are tied to skills, not agents directly. Use the agent's attached
        // skills to infer a review signal.
        $skillIds = DB::table('agent_skill')
            ->where('agent_id', $agent->id)
            ->pluck('skill_id');

        if ($skillIds->isEmpty()) {
            return null;
        }

        if (! DB::getSchemaBuilder()->hasTable('skill_reviews')) {
            return null;
        }

        $reviews = DB::table('skill_reviews')
            ->whereIn('skill_id', $skillIds)
            ->select('status')
            ->get();

        if ($reviews->count() === 0) {
            return null;
        }

        $approved = $reviews->where('status', 'approved')->count();
        $rejected = $reviews->where('status', 'rejected')->count();
        $net = $approved - $rejected;

        return max(-1.0, min(1.0, $net / max(1, $reviews->count())));
    }
}
