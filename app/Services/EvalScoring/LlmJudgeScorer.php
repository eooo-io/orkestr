<?php

namespace App\Services\EvalScoring;

use App\Models\SkillEvalPrompt;
use App\Services\LLM\LLMProviderFactory;
use JsonException;

/**
 * Opt-in LLM-as-judge scorer. Uses a second LLM call to grade the response
 * against the prompt's `grading_criteria`. More expensive + non-deterministic;
 * leave turned off by default.
 */
class LlmJudgeScorer implements ScorerInterface
{
    public const DEFAULT_JUDGE_MODEL = 'claude-sonnet-4-6';

    public function __construct(
        protected LLMProviderFactory $factory,
    ) {}

    public function score(SkillEvalPrompt $prompt, string $response): array
    {
        $criteria = $prompt->grading_criteria ?? [];
        $criteriaText = is_array($criteria) ? implode("\n- ", $criteria) : (string) $criteria;

        $judgeModel = config('eval.judge_model', self::DEFAULT_JUDGE_MODEL);
        $provider = $this->factory->make($judgeModel);

        $system = <<<'SYS'
You are a strict grader. Given a user prompt, an expected behavior, and a model's response, output STRICT JSON:
{"score": <0-100 integer>, "reasoning": "<one short sentence>"}
No prose outside the JSON.
SYS;

        $userMsg = "Prompt:\n{$prompt->prompt}\n\nExpected behavior:\n" . ($prompt->expected_behavior ?? '—')
            . "\n\nGrading criteria:\n- {$criteriaText}\n\nResponse to grade:\n{$response}";

        try {
            $response = $provider->chat(
                $system,
                [['role' => 'user', 'content' => $userMsg]],
                $judgeModel,
                300,
            );
        } catch (\Throwable $e) {
            return [
                'score' => 0,
                'reasoning' => 'Judge call failed: ' . $e->getMessage(),
                'signals' => ['scorer' => 'llm_judge', 'judge_error' => true, 'judge_model' => $judgeModel],
            ];
        }

        $judgeOutput = $this->extractText($response);
        $parsed = $this->parseJudgeJson($judgeOutput);
        if ($parsed === null) {
            return [
                'score' => 0,
                'reasoning' => 'Judge returned unparseable output.',
                'signals' => [
                    'scorer' => 'llm_judge',
                    'judge_model' => $judgeModel,
                    'raw_output' => mb_substr($judgeOutput, 0, 500),
                ],
            ];
        }

        return [
            'score' => max(0, min(100, (int) ($parsed['score'] ?? 0))),
            'reasoning' => (string) ($parsed['reasoning'] ?? ''),
            'signals' => [
                'scorer' => 'llm_judge',
                'judge_model' => $judgeModel,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function extractText(array $response): string
    {
        $text = '';
        foreach (($response['content'] ?? []) as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= $block['text'] ?? '';
            }
        }

        return $text;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function parseJudgeJson(string $output): ?array
    {
        $trimmed = trim($output);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/\{.*\}/s', $trimmed, $matches)) {
            $trimmed = $matches[0];
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
