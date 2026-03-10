<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Skill;
use App\Services\SkillCompositionService;
use Anthropic;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SkillTestController extends Controller
{
    public function __construct(
        protected SkillCompositionService $compositionService,
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
        $apiKey = AppSetting::get('anthropic_api_key') ?: config('services.anthropic.api_key') ?: env('ANTHROPIC_API_KEY');

        if (empty($apiKey)) {
            return new StreamedResponse(function () {
                echo "data: " . json_encode(['type' => 'error', 'error' => 'Anthropic API key not configured. Set it in Settings.']) . "\n\n";
                ob_flush();
                flush();
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        return new StreamedResponse(function () use ($apiKey, $model, $maxTokens, $systemPrompt, $messages) {
            try {
                $client = Anthropic::factory()
                    ->withApiKey($apiKey)
                    ->make();

                $params = [
                    'model' => $model,
                    'max_tokens' => $maxTokens,
                    'messages' => $messages,
                ];

                if (! empty($systemPrompt)) {
                    $params['system'] = $systemPrompt;
                }

                $stream = $client->messages()->createStreamed($params);

                foreach ($stream as $response) {
                    $type = $response->type;

                    if ($type === 'content_block_delta') {
                        $text = $response->delta->text ?? '';
                        if ($text !== '') {
                            echo "data: " . json_encode(['type' => 'delta', 'text' => $text]) . "\n\n";
                            ob_flush();
                            flush();
                        }
                    } elseif ($type === 'message_start') {
                        $inputTokens = $response->message->usage->inputTokens ?? null;
                        echo "data: " . json_encode(['type' => 'message_start', 'input_tokens' => $inputTokens]) . "\n\n";
                        ob_flush();
                        flush();
                    } elseif ($type === 'message_delta') {
                        $outputTokens = $response->usage->outputTokens ?? null;
                        $stopReason = $response->delta->stopReason ?? null;
                        echo "data: " . json_encode(['type' => 'message_delta', 'output_tokens' => $outputTokens, 'stop_reason' => $stopReason]) . "\n\n";
                        ob_flush();
                        flush();
                    }
                }

                echo "data: " . json_encode(['type' => 'done']) . "\n\n";
                ob_flush();
                flush();
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
