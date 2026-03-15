<?php

namespace App\Http\Controllers;

use App\Services\LLM\AirGapService;
use App\Services\LLM\LLMProviderFactory;
use App\Services\LLM\LocalModelBrowserService;
use App\Services\LLM\ModelComparisonService;
use App\Services\LLM\ModelHealthCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModelHealthController extends Controller
{
    public function __construct(
        protected ModelHealthCheckService $healthService,
        protected LLMProviderFactory $factory,
    ) {}

    /**
     * Check health of all providers.
     */
    public function checkAll(): JsonResponse
    {
        return response()->json([
            'data' => $this->healthService->checkAll(),
        ]);
    }

    /**
     * Check health of a specific provider.
     */
    public function checkProvider(string $provider): JsonResponse
    {
        return response()->json([
            'data' => $this->healthService->checkProvider($provider),
        ]);
    }

    /**
     * Benchmark a specific model.
     */
    public function benchmark(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'model' => 'required|string|max:255',
        ]);

        $provider = $this->factory->make($validated['model']);
        $result = $this->healthService->benchmark($validated['model'], $provider);

        return response()->json(['data' => $result]);
    }

    /**
     * Compare multiple models.
     */
    public function compare(Request $request, ModelComparisonService $comparisonService): JsonResponse
    {
        $validated = $request->validate([
            'models' => 'required|array|min:1|max:10',
            'models.*' => 'string|max:255',
            'prompt' => 'nullable|string|max:2000',
        ]);

        $result = $comparisonService->compare(
            $validated['models'],
            $validated['prompt'] ?? null,
        );

        return response()->json(['data' => $result]);
    }

    /**
     * Browse locally available models.
     */
    public function localModels(LocalModelBrowserService $browserService): JsonResponse
    {
        return response()->json([
            'data' => $browserService->discover(),
        ]);
    }

    /**
     * Get detailed info about a specific Ollama model.
     */
    public function ollamaModelDetail(string $model, LocalModelBrowserService $browserService): JsonResponse
    {
        $detail = $browserService->showOllamaModel($model);

        if (! $detail) {
            return response()->json(['error' => 'Model not found'], 404);
        }

        return response()->json(['data' => $detail]);
    }

    /**
     * Get air-gap mode status.
     */
    public function airGapStatus(AirGapService $airGapService): JsonResponse
    {
        return response()->json([
            'data' => $airGapService->status(),
        ]);
    }

    /**
     * Toggle air-gap mode.
     */
    public function airGapToggle(Request $request, AirGapService $airGapService): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        if ($validated['enabled']) {
            $airGapService->enable();
        } else {
            $airGapService->disable();
        }

        return response()->json([
            'data' => $airGapService->status(),
        ]);
    }
}
