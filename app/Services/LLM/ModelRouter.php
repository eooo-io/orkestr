<?php

namespace App\Services\LLM;

class ModelRouter
{
    public function __construct(
        private LLMProviderFactory $factory,
        private ProviderHealthMonitor $healthMonitor,
    ) {}

    /**
     * Attempt an LLM chat call with fallback support.
     *
     * Tries the primary model first, then falls back through the chain.
     * Returns the response array with an additional 'model_used' key.
     */
    public function chatWithFallback(
        string $systemPrompt,
        array $messages,
        string $primaryModel,
        int $maxTokens,
        array $tools = [],
        array $fallbackChain = [],
    ): array {
        $models = array_merge([$primaryModel], $fallbackChain);
        $lastException = null;

        foreach ($models as $model) {
            $providerName = $this->factory->providerName($model);

            // Skip models whose provider is down (but always try the primary)
            if ($model !== $primaryModel && ! $this->healthMonitor->isHealthy($providerName)) {
                continue;
            }

            try {
                $provider = $this->factory->make($model);
                $startTime = microtime(true);
                $response = $provider->chat($systemPrompt, $messages, $model, $maxTokens, $tools);
                $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

                $this->healthMonitor->recordSuccess($providerName, $latencyMs);

                $response['model_used'] = $model;

                return $response;
            } catch (\Throwable $e) {
                $this->healthMonitor->recordFailure($providerName, $e->getMessage());
                $lastException = $e;
                // Continue to next model in chain
            }
        }

        // All models failed
        throw $lastException ?? new \RuntimeException('No models available in fallback chain');
    }
}
