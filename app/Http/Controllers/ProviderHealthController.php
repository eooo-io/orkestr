<?php

namespace App\Http\Controllers;

use App\Services\LLM\LLMProviderFactory;
use App\Services\LLM\ProviderHealthMonitor;
use Illuminate\Http\JsonResponse;

class ProviderHealthController extends Controller
{
    public function __construct(
        protected ProviderHealthMonitor $monitor,
        protected LLMProviderFactory $factory,
    ) {}

    /**
     * GET /api/provider-health
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->monitor->getAllStatus(),
        ]);
    }

    /**
     * POST /api/provider-health/check/{provider}
     */
    public function check(string $provider): JsonResponse
    {
        $validProviders = ['anthropic', 'openai', 'gemini', 'ollama'];

        if (! in_array($provider, $validProviders)) {
            return response()->json([
                'message' => "Unknown provider: {$provider}",
            ], 422);
        }

        try {
            $startMs = (int) round(microtime(true) * 1000);

            // Use a known model prefix to create the right provider instance
            $modelPrefix = match ($provider) {
                'anthropic' => 'claude-sonnet-4-6',
                'openai' => 'gpt-5.4',
                'gemini' => 'gemini-3-flash',
                'ollama' => 'ollama-default',
            };

            $providerInstance = $this->factory->make($modelPrefix);
            $providerInstance->models();

            $latencyMs = (int) round(microtime(true) * 1000) - $startMs;
            $this->monitor->recordSuccess($provider, $latencyMs);
        } catch (\Throwable $e) {
            $this->monitor->recordFailure($provider, $e->getMessage());
        }

        return response()->json([
            'data' => $this->monitor->getStatus($provider),
        ]);
    }
}
