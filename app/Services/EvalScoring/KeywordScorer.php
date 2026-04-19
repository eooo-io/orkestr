<?php

namespace App\Services\EvalScoring;

use App\Models\SkillEvalPrompt;

/**
 * Deterministic scoring pass: extracts expected keywords from a prompt's
 * `expected_behavior` text and rewards their presence in the response.
 * Cheap, reproducible, and good enough to gate regressions.
 */
class KeywordScorer implements ScorerInterface
{
    public function score(SkillEvalPrompt $prompt, string $response): array
    {
        $expected = trim((string) ($prompt->expected_behavior ?? ''));

        if ($expected === '' || trim($response) === '') {
            return [
                'score' => 0,
                'reasoning' => $expected === ''
                    ? 'No expected_behavior defined; cannot score.'
                    : 'Empty model response.',
                'signals' => [
                    'scorer' => 'keyword',
                    'expected_keywords' => [],
                    'matched' => [],
                    'missing' => [],
                ],
            ];
        }

        $keywords = $this->extractKeywords($expected);

        if (empty($keywords)) {
            return [
                'score' => 50,
                'reasoning' => 'No usable keywords extracted from expected_behavior; returning neutral.',
                'signals' => [
                    'scorer' => 'keyword',
                    'expected_keywords' => [],
                    'matched' => [],
                    'missing' => [],
                ],
            ];
        }

        $normalizedResponse = mb_strtolower($response);
        $matched = [];
        $missing = [];

        foreach ($keywords as $keyword) {
            if (mb_strpos($normalizedResponse, $keyword) !== false) {
                $matched[] = $keyword;
            } else {
                $missing[] = $keyword;
            }
        }

        $score = (int) round((count($matched) / count($keywords)) * 100);

        return [
            'score' => $score,
            'reasoning' => sprintf(
                'Matched %d of %d expected keywords.',
                count($matched),
                count($keywords),
            ),
            'signals' => [
                'scorer' => 'keyword',
                'expected_keywords' => $keywords,
                'matched' => $matched,
                'missing' => $missing,
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function extractKeywords(string $text): array
    {
        $stopwords = [
            'the', 'a', 'an', 'and', 'or', 'but', 'of', 'to', 'in', 'on',
            'at', 'for', 'with', 'by', 'from', 'is', 'are', 'was', 'were',
            'be', 'been', 'being', 'has', 'have', 'had', 'do', 'does',
            'did', 'will', 'would', 'should', 'could', 'may', 'might',
            'this', 'that', 'these', 'those', 'it', 'its', 'your',
            'response', 'output', 'must', 'contain', 'mentions', 'include',
        ];

        $tokens = preg_split('/[^\p{L}\p{N}\-]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $tokens,
            fn (string $t) => mb_strlen($t) >= 3 && ! in_array($t, $stopwords, true),
        )));
    }
}
