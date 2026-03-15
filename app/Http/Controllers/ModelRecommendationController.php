<?php

namespace App\Http\Controllers;

use App\Services\LLM\LocalModelBrowserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModelRecommendationController extends Controller
{
    protected array $recommendations = [
        'chat' => [
            ['model' => 'claude-sonnet-4-6', 'provider' => 'anthropic', 'reason' => 'Excellent conversational ability with fast response times', 'size_gb' => null],
            ['model' => 'gpt-5.4', 'provider' => 'openai', 'reason' => 'Strong general-purpose chat model with broad knowledge', 'size_gb' => null],
            ['model' => 'llama3.3:latest', 'provider' => 'ollama', 'reason' => 'High-quality open-source chat model, runs locally', 'size_gb' => 4.7],
            ['model' => 'mistral:latest', 'provider' => 'ollama', 'reason' => 'Fast and capable local chat model', 'size_gb' => 4.1],
            ['model' => 'gemma2:latest', 'provider' => 'ollama', 'reason' => 'Compact Google model good for conversational tasks', 'size_gb' => 5.4],
        ],
        'code' => [
            ['model' => 'claude-sonnet-4-6', 'provider' => 'anthropic', 'reason' => 'Top-tier code generation and understanding', 'size_gb' => null],
            ['model' => 'claude-opus-4-6', 'provider' => 'anthropic', 'reason' => 'Best for complex multi-file code tasks', 'size_gb' => null],
            ['model' => 'codellama:latest', 'provider' => 'ollama', 'reason' => 'Purpose-built for code generation, runs locally', 'size_gb' => 3.8],
            ['model' => 'deepseek-coder-v2:latest', 'provider' => 'ollama', 'reason' => 'Excellent code completion and generation', 'size_gb' => 8.9],
            ['model' => 'qwen2.5-coder:latest', 'provider' => 'ollama', 'reason' => 'Strong coding model with good instruction following', 'size_gb' => 4.7],
        ],
        'summarization' => [
            ['model' => 'claude-sonnet-4-6', 'provider' => 'anthropic', 'reason' => 'Precise and concise summaries with high fidelity', 'size_gb' => null],
            ['model' => 'gemini-3.1-pro', 'provider' => 'gemini', 'reason' => 'Large context window ideal for long documents', 'size_gb' => null],
            ['model' => 'llama3.3:latest', 'provider' => 'ollama', 'reason' => 'Good summarization quality for local processing', 'size_gb' => 4.7],
            ['model' => 'mistral:latest', 'provider' => 'ollama', 'reason' => 'Fast local summarization with decent quality', 'size_gb' => 4.1],
        ],
        'translation' => [
            ['model' => 'claude-opus-4-6', 'provider' => 'anthropic', 'reason' => 'Excellent multilingual capabilities and nuance', 'size_gb' => null],
            ['model' => 'gpt-5.4', 'provider' => 'openai', 'reason' => 'Strong multilingual translation across many languages', 'size_gb' => null],
            ['model' => 'llama3.3:latest', 'provider' => 'ollama', 'reason' => 'Good multilingual support for common languages', 'size_gb' => 4.7],
            ['model' => 'aya:latest', 'provider' => 'ollama', 'reason' => 'Specialized multilingual model covering 100+ languages', 'size_gb' => 4.8],
        ],
        'analysis' => [
            ['model' => 'claude-opus-4-6', 'provider' => 'anthropic', 'reason' => 'Deep analytical reasoning and structured output', 'size_gb' => null],
            ['model' => 'o3', 'provider' => 'openai', 'reason' => 'Advanced reasoning model for complex analysis', 'size_gb' => null],
            ['model' => 'gemini-3.1-pro', 'provider' => 'gemini', 'reason' => 'Strong analytical capabilities with large context', 'size_gb' => null],
            ['model' => 'llama3.3:latest', 'provider' => 'ollama', 'reason' => 'Capable local model for data analysis tasks', 'size_gb' => 4.7],
        ],
        'creative' => [
            ['model' => 'claude-opus-4-6', 'provider' => 'anthropic', 'reason' => 'Exceptional creative writing and ideation', 'size_gb' => null],
            ['model' => 'gpt-5.4', 'provider' => 'openai', 'reason' => 'Versatile creative content generation', 'size_gb' => null],
            ['model' => 'llama3.3:latest', 'provider' => 'ollama', 'reason' => 'Good creative writing for local use', 'size_gb' => 4.7],
            ['model' => 'mistral:latest', 'provider' => 'ollama', 'reason' => 'Fast creative text generation locally', 'size_gb' => 4.1],
        ],
    ];

    /**
     * Get model recommendations for a given task type.
     */
    public function index(Request $request, LocalModelBrowserService $browserService): JsonResponse
    {
        $taskType = $request->query('task_type', 'chat');

        $validTypes = array_keys($this->recommendations);
        if (! in_array($taskType, $validTypes)) {
            return response()->json([
                'error' => 'Invalid task type. Valid types: ' . implode(', ', $validTypes),
            ], 422);
        }

        // Get locally available Ollama models
        $localModels = collect($browserService->discoverOllama())
            ->pluck('name')
            ->map(fn ($name) => strtolower($name))
            ->toArray();

        $recommendations = array_map(function ($rec) use ($localModels) {
            $modelBase = strtolower($rec['model']);

            // Check if the model (or a variant) is available locally
            $localAvailable = false;
            if ($rec['provider'] === 'ollama') {
                foreach ($localModels as $local) {
                    if ($local === $modelBase || str_starts_with($local, explode(':', $modelBase)[0])) {
                        $localAvailable = true;
                        break;
                    }
                }
            }

            return [
                'model' => $rec['model'],
                'provider' => $rec['provider'],
                'reason' => $rec['reason'],
                'size_gb' => $rec['size_gb'],
                'local_available' => $localAvailable,
            ];
        }, $this->recommendations[$taskType]);

        return response()->json(['data' => $recommendations]);
    }
}
