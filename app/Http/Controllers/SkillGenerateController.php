<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Anthropic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillGenerateController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'description' => 'required|string|max:2000',
            'constraints' => 'nullable|string|max:1000',
        ]);

        $apiKey = AppSetting::get('anthropic_api_key') ?: config('services.anthropic.api_key') ?: env('ANTHROPIC_API_KEY');

        if (empty($apiKey)) {
            return response()->json([
                'message' => 'Anthropic API key not configured. Set it in Settings.',
            ], 422);
        }

        $systemPrompt = <<<'PROMPT'
You are an expert AI prompt engineer. The user will describe what they want a skill (system prompt) to do. Generate a complete skill definition.

Return ONLY valid JSON with this exact structure:
{
  "name": "Short descriptive name",
  "description": "One-sentence description of what the skill does",
  "model": null,
  "max_tokens": null,
  "tags": ["tag1", "tag2"],
  "body": "The full system prompt text..."
}

Guidelines for the body (system prompt):
- Be specific and actionable, not vague
- Include clear output format expectations where appropriate
- Use structured sections (with markdown headings) for complex skills
- Keep instructions focused — avoid contradictory directives
- Include relevant constraints and boundaries
- Aim for 200-800 words depending on complexity

Do not wrap the JSON in markdown code fences. Return raw JSON only.
PROMPT;

        $userMessage = "Generate a skill for: {$validated['description']}";
        if (! empty($validated['constraints'])) {
            $userMessage .= "\n\nAdditional constraints: {$validated['constraints']}";
        }

        try {
            $client = Anthropic::factory()
                ->withApiKey($apiKey)
                ->make();

            $response = $client->messages()->create([
                'model' => 'claude-sonnet-4-6',
                'max_tokens' => 2048,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userMessage],
                ],
            ]);

            $text = $response->content[0]->text ?? '';

            // Parse the JSON response
            $generated = json_decode($text, true);

            if (! $generated || ! isset($generated['name'], $generated['body'])) {
                return response()->json([
                    'message' => 'Failed to parse generated skill. Please try again.',
                ], 422);
            }

            return response()->json(['data' => $generated]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Generation failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
