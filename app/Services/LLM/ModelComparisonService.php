<?php

namespace App\Services\LLM;

use App\Models\AppSetting;

/**
 * Compares model performance across providers using standardized benchmarks.
 */
class ModelComparisonService
{
    public function __construct(
        protected LLMProviderFactory $factory,
        protected ModelHealthCheckService $healthService,
    ) {}

    /**
     * Run a comparison benchmark across multiple models.
     *
     * @param  string[]  $models
     * @return array{results: array, summary: array}
     */
    public function compare(array $models, ?string $prompt = null): array
    {
        $prompt = $prompt ?? 'Explain what a REST API is in exactly two sentences.';
        $results = [];

        foreach ($models as $model) {
            try {
                $provider = $this->factory->make($model);
                $result = $this->runBenchmark($model, $provider, $prompt);
                $results[] = $result;
            } catch (\Throwable $e) {
                $results[] = [
                    'model' => $model,
                    'provider' => $this->factory->providerName($model),
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'latency_ms' => null,
                    'time_to_first_token_ms' => null,
                    'tokens_per_second' => null,
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                    'output_text' => null,
                    'output_length' => 0,
                ];
            }
        }

        return [
            'results' => $results,
            'summary' => $this->summarize($results),
            'prompt' => $prompt,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Run a single model benchmark.
     */
    protected function runBenchmark(string $model, LLMProviderInterface $provider, string $prompt): array
    {
        $start = microtime(true);
        $firstTokenTime = null;
        $outputText = '';
        $inputTokens = 0;
        $outputTokens = 0;

        $generator = $provider->stream(
            'You are a helpful assistant. Be concise.',
            [['role' => 'user', 'content' => $prompt]],
            $model,
            256,
        );

        foreach ($generator as $event) {
            if ($event['type'] === 'text') {
                if ($firstTokenTime === null) {
                    $firstTokenTime = microtime(true);
                }
                $outputText .= $event['text'];
            }

            if ($event['type'] === 'usage') {
                $inputTokens = $event['input_tokens'] ?? 0;
                $outputTokens = $event['output_tokens'] ?? 0;
            }
        }

        $totalMs = round((microtime(true) - $start) * 1000, 2);
        $ttftMs = $firstTokenTime ? round(($firstTokenTime - $start) * 1000, 2) : null;

        // Estimate tokens if usage wasn't reported
        if ($outputTokens === 0 && strlen($outputText) > 0) {
            $outputTokens = (int) ceil(strlen($outputText) / 4);
        }

        $generationTimeMs = $ttftMs ? ($totalMs - $ttftMs) : $totalMs;

        return [
            'model' => $model,
            'provider' => $this->factory->providerName($model),
            'status' => 'success',
            'error' => null,
            'latency_ms' => $totalMs,
            'time_to_first_token_ms' => $ttftMs,
            'tokens_per_second' => ($outputTokens > 0 && $generationTimeMs > 0)
                ? round($outputTokens / ($generationTimeMs / 1000), 2)
                : null,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'output_text' => $outputText,
            'output_length' => strlen($outputText),
        ];
    }

    /**
     * Summarize comparison results.
     */
    protected function summarize(array $results): array
    {
        $successful = array_filter($results, fn ($r) => $r['status'] === 'success');

        if (empty($successful)) {
            return [
                'fastest' => null,
                'fastest_ttft' => null,
                'highest_throughput' => null,
                'most_verbose' => null,
            ];
        }

        $byLatency = $successful;
        usort($byLatency, fn ($a, $b) => ($a['latency_ms'] ?? PHP_INT_MAX) <=> ($b['latency_ms'] ?? PHP_INT_MAX));

        $byTtft = array_filter($successful, fn ($r) => $r['time_to_first_token_ms'] !== null);
        usort($byTtft, fn ($a, $b) => $a['time_to_first_token_ms'] <=> $b['time_to_first_token_ms']);

        $byThroughput = array_filter($successful, fn ($r) => $r['tokens_per_second'] !== null);
        usort($byThroughput, fn ($a, $b) => ($b['tokens_per_second'] ?? 0) <=> ($a['tokens_per_second'] ?? 0));

        $byLength = $successful;
        usort($byLength, fn ($a, $b) => ($b['output_length'] ?? 0) <=> ($a['output_length'] ?? 0));

        return [
            'fastest' => ! empty($byLatency) ? $byLatency[0]['model'] : null,
            'fastest_ttft' => ! empty($byTtft) ? $byTtft[0]['model'] : null,
            'highest_throughput' => ! empty($byThroughput) ? $byThroughput[0]['model'] : null,
            'most_verbose' => ! empty($byLength) ? $byLength[0]['model'] : null,
        ];
    }
}
