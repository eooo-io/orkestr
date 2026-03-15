<?php

namespace App\Services;

use App\Models\Skill;
use App\Services\LLM\LLMProviderFactory;

class CrossModelBenchmarkService
{
    public function __construct(
        private LLMProviderFactory $providerFactory,
    ) {}

    /**
     * Benchmark a skill across multiple models.
     * Returns comparison data for each model.
     */
    public function benchmarkSkill(Skill $skill, array $models): array
    {
        $prompt = $skill->body ?? '';
        $results = [];

        foreach ($models as $model) {
            $startTime = microtime(true);
            $result = $this->runBenchmark($prompt, $model);
            $elapsedMs = round((microtime(true) - $startTime) * 1000, 1);

            $results[] = [
                'model' => $model,
                'latency_ms' => $elapsedMs,
                'output_length' => strlen($result['output'] ?? ''),
                'tokens_used' => $result['tokens_used'] ?? null,
                'estimated_cost_microcents' => $result['estimated_cost_microcents'] ?? null,
                'output_preview' => mb_substr($result['output'] ?? '', 0, 500),
                'success' => $result['success'],
                'error' => $result['error'] ?? null,
            ];
        }

        return [
            'skill_id' => $skill->id,
            'skill_name' => $skill->name,
            'models_tested' => count($models),
            'results' => $results,
        ];
    }

    private function runBenchmark(string $prompt, string $model): array
    {
        try {
            $provider = $this->providerFactory->make($model);
            $messages = [['role' => 'user', 'content' => $prompt]];
            $response = $provider->chat($messages, $model, [
                'max_tokens' => 1024,
                'temperature' => 0.0,
            ]);

            $output = $response['content'] ?? '';
            $tokensUsed = strlen($output) / 4; // approximation

            return [
                'success' => true,
                'output' => $output,
                'tokens_used' => (int) $tokensUsed,
                'estimated_cost_microcents' => (int) ($tokensUsed * 0.3), // rough estimate
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
            ];
        }
    }
}
