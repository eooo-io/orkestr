<?php

namespace App\Services\EvalScoring;

use App\Models\SkillEvalSuite;

class ScorerFactory
{
    public const SCORER_KEYWORD = 'keyword';
    public const SCORER_LLM_JUDGE = 'llm_judge';

    public function __construct(
        protected KeywordScorer $keyword,
        protected LlmJudgeScorer $llmJudge,
    ) {}

    public function forSuite(SkillEvalSuite $suite): ScorerInterface
    {
        return match ($suite->scorer) {
            self::SCORER_LLM_JUDGE => $this->llmJudge,
            default => $this->keyword,
        };
    }
}
