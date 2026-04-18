<?php

namespace App\Services\EvalScoring;

use App\Models\SkillEvalPrompt;

interface ScorerInterface
{
    /**
     * Grade a model's response against a prompt's expectations.
     *
     * @return array{score: int, reasoning: string, signals: array<string, mixed>}
     */
    public function score(SkillEvalPrompt $prompt, string $response): array;
}
