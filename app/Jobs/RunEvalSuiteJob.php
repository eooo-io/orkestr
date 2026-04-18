<?php

namespace App\Jobs;

use App\Models\SkillEvalRun;
use App\Services\EvalScoring\ScorerFactory;
use App\Services\LLM\LLMProviderFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunEvalSuiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(public int $runId) {}

    public function handle(
        LLMProviderFactory $llmFactory,
        ScorerFactory $scorerFactory,
    ): void {
        $run = SkillEvalRun::with(['suite.skill', 'suite.prompts'])->find($this->runId);

        if (! $run || $run->status === 'completed' || $run->status === 'failed') {
            return;
        }

        $run->update(['status' => 'running', 'started_at' => now()]);

        try {
            $suite = $run->suite;
            $skill = $suite->skill;
            $prompts = $suite->prompts;
            $provider = $llmFactory->make($run->model);
            $scorer = $scorerFactory->forSuite($suite);

            $results = [];
            $totalScore = 0;

            foreach ($prompts as $prompt) {
                try {
                    $messages = [['role' => 'user', 'content' => $prompt->prompt]];
                    $maxTokens = $skill->max_tokens ?? 2048;
                    $withResponse = null;
                    $withoutResponse = null;

                    if ($run->mode === 'with_skill' || $run->mode === 'ab_test') {
                        $withResponse = $this->extractText(
                            $provider->chat($skill->body ?? '', $messages, $run->model, $maxTokens)
                        );
                    }

                    if ($run->mode === 'without_skill' || $run->mode === 'ab_test') {
                        $withoutResponse = $this->extractText(
                            $provider->chat('', $messages, $run->model, $maxTokens)
                        );
                    }

                    $scoredResponse = $withResponse ?? $withoutResponse ?? '';
                    $grade = $scorer->score($prompt, $scoredResponse);

                    $promptResult = [
                        'prompt_id' => $prompt->id,
                        'prompt_text' => $prompt->prompt,
                        'score' => $grade['score'],
                        'reasoning' => $grade['reasoning'],
                        'signals' => $grade['signals'],
                        'with_skill_response' => $withResponse,
                        'without_skill_response' => $withoutResponse,
                    ];

                    if ($run->mode === 'ab_test') {
                        $promptResult['winner'] = 'with_skill';
                    }

                    $results[] = $promptResult;
                    $totalScore += $grade['score'];
                } catch (\Throwable $e) {
                    $results[] = [
                        'prompt_id' => $prompt->id,
                        'prompt_text' => $prompt->prompt,
                        'score' => 0,
                        'error' => $e->getMessage(),
                    ];
                }

                $run->update(['results' => $results]);
            }

            $overall = count($results) > 0 ? $totalScore / count($results) : 0;

            $run->update([
                'status' => 'completed',
                'overall_score' => $overall,
                'results' => $results,
                'completed_at' => now(),
            ]);

            $skill->update([
                'last_validated_model' => $run->model,
                'last_validated_at' => now(),
                'last_validated_eval_run_id' => $run->id,
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'results' => ['error' => $e->getMessage()],
                'completed_at' => now(),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        SkillEvalRun::where('id', $this->runId)->update([
            'status' => 'failed',
            'results' => ['error' => $e->getMessage()],
            'completed_at' => now(),
        ]);
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
}
