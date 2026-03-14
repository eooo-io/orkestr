<?php

namespace App\Services\LLM;

use Illuminate\Support\Facades\Cache;

class ProviderHealthMonitor
{
    protected const CACHE_PREFIX = 'provider_health:';

    protected const CACHE_TTL_SECONDS = 300; // 5 minutes

    protected const DEGRADED_THRESHOLD = 3;

    protected const DOWN_THRESHOLD = 5;

    protected const PROVIDERS = ['anthropic', 'openai', 'gemini', 'ollama'];

    /**
     * Record a successful provider call.
     */
    public function recordSuccess(string $provider, int $latencyMs): void
    {
        $current = $this->getStatus($provider);

        $avgLatency = $current['avg_latency_ms'] > 0
            ? (int) round(($current['avg_latency_ms'] + $latencyMs) / 2)
            : $latencyMs;

        Cache::put(self::CACHE_PREFIX.$provider, [
            'status' => 'healthy',
            'error_count' => 0,
            'last_error' => null,
            'last_success_at' => now()->toIso8601String(),
            'avg_latency_ms' => $avgLatency,
            'updated_at' => now()->toIso8601String(),
        ], self::CACHE_TTL_SECONDS);
    }

    /**
     * Record a provider failure.
     */
    public function recordFailure(string $provider, string $error): void
    {
        $current = $this->getStatus($provider);
        $errorCount = $current['error_count'] + 1;

        $status = match (true) {
            $errorCount >= self::DOWN_THRESHOLD => 'down',
            $errorCount >= self::DEGRADED_THRESHOLD => 'degraded',
            default => 'healthy',
        };

        Cache::put(self::CACHE_PREFIX.$provider, [
            'status' => $status,
            'error_count' => $errorCount,
            'last_error' => $error,
            'last_success_at' => $current['last_success_at'],
            'avg_latency_ms' => $current['avg_latency_ms'],
            'updated_at' => now()->toIso8601String(),
        ], self::CACHE_TTL_SECONDS);
    }

    /**
     * Check if a provider is healthy (not down).
     */
    public function isHealthy(string $provider): bool
    {
        return $this->getStatus($provider)['status'] !== 'down';
    }

    /**
     * Check if a provider is degraded.
     */
    public function isDegraded(string $provider): bool
    {
        return $this->getStatus($provider)['status'] === 'degraded';
    }

    /**
     * Get health status for a single provider.
     */
    public function getStatus(string $provider): array
    {
        return Cache::get(self::CACHE_PREFIX.$provider, [
            'status' => 'healthy',
            'error_count' => 0,
            'last_error' => null,
            'last_success_at' => null,
            'avg_latency_ms' => 0,
            'updated_at' => null,
        ]);
    }

    /**
     * Get health status for all providers.
     */
    public function getAllStatus(): array
    {
        $result = [];

        foreach (self::PROVIDERS as $provider) {
            $result[$provider] = $this->getStatus($provider);
        }

        return $result;
    }

    /**
     * Reset health state for a provider.
     */
    public function reset(string $provider): void
    {
        Cache::forget(self::CACHE_PREFIX.$provider);
    }
}
