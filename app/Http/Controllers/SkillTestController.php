<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Skill;
use App\Services\LLM\LLMProviderFactory;
use App\Services\SkillCompositionService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SkillTestController extends Controller
{
    public function __construct(
        protected SkillCompositionService $compositionService,
        protected LLMProviderFactory $providerFactory,
    ) {}

    /**
     * Test a single skill — legacy endpoint used by LiveTestPanel.
     */
    public function __invoke(Request $request, Skill $skill): StreamedResponse
    {
        $validated = $request->validate([
            'user_message' => 'required|string|max:10000',
        ]);

        $model = $skill->model ?: AppSetting::get('default_model', 'claude-sonnet-4-20250514');
        $maxTokens = $skill->max_tokens ?: 1024;
        $systemPrompt = $this->compositionService->resolve($skill);

        return $this->stream($model, $maxTokens, $systemPrompt, [
            ['role' => 'user', 'content' => $validated['user_message']],
        ]);
    }

    /**
     * Playground endpoint — supports custom system prompt, multi-turn, model override.
     */
    public function playground(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'system_prompt' => 'nullable|string|max:500000',
            'messages' => 'required|array|min:1',
            'messages.*.role' => 'required|string|in:user,assistant',
            'messages.*.content' => 'required|string|max:50000',
            'model' => 'nullable|string|max:100',
            'max_tokens' => 'nullable|integer|min:1|max:128000',
        ]);

        $model = $validated['model'] ?: AppSetting::get('default_model', 'claude-sonnet-4-20250514');
        $maxTokens = $validated['max_tokens'] ?: 4096;
        $systemPrompt = $validated['system_prompt'] ?? '';

        return $this->stream($model, $maxTokens, $systemPrompt, $validated['messages']);
    }

    protected function stream(string $model, int $maxTokens, string $systemPrompt, array $messages): StreamedResponse
    {
        return new StreamedResponse(function () use ($model, $maxTokens, $systemPrompt, $messages) {
            try {
                $provider = $this->providerFactory->make($model);
                $generator = $provider->stream($systemPrompt, $messages, $model, $maxTokens);

                $inputTokens = null;
                $outputTokens = null;

                foreach ($generator as $event) {
                    $type = $event['type'];

                    if ($type === 'text') {
                        echo "data: " . json_encode(['type' => 'delta', 'text' => $event['text']]) . "\n\n";
                        ob_flush();
                        flush();
                    } elseif ($type === 'usage') {
                        // Track tokens — providers may yield usage multiple times
                        if (isset($event['input_tokens']) && $event['input_tokens'] > 0) {
                            $inputTokens = $event['input_tokens'];
                            echo "data: " . json_encode(['type' => 'message_start', 'input_tokens' => $inputTokens]) . "\n\n";
                            ob_flush();
                            flush();
                        }
                        if (isset($event['output_tokens']) && $event['output_tokens'] > 0) {
                            $outputTokens = $event['output_tokens'];
                            echo "data: " . json_encode([
                                'type' => 'message_delta',
                                'output_tokens' => $outputTokens,
                                'stop_reason' => $event['stop_reason'] ?? null,
                            ]) . "\n\n";
                            ob_flush();
                            flush();
                        }
                    } elseif ($type === 'done') {
                        echo "data: " . json_encode(['type' => 'done']) . "\n\n";
                        ob_flush();
                        flush();
                    }
                }
            } catch (\Throwable $e) {
                echo "data: " . json_encode(['type' => 'error', 'error' => $e->getMessage()]) . "\n\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
