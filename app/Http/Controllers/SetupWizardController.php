<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AppSetting;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SetupWizardController extends Controller
{
    /**
     * GET /api/setup/status
     * Returns whether setup has been completed and current step progress.
     */
    public function status(): JsonResponse
    {
        $isComplete = AppSetting::get('setup_completed', false);

        return response()->json([
            'completed' => (bool) $isComplete,
            'completed_at' => AppSetting::get('setup_completed_at'),
            'steps' => [
                'api_keys' => ! empty(AppSetting::get('anthropic_api_key')) || ! empty(AppSetting::get('openai_api_key')),
                'default_model' => ! empty(AppSetting::get('default_model')),
                'first_project' => Project::count() > 0,
                'first_agent' => Agent::count() > 0,
            ],
        ]);
    }

    /**
     * POST /api/setup/api-keys
     * Step 1: Configure LLM API keys.
     */
    public function configureApiKeys(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'anthropic_api_key' => 'nullable|string',
            'openai_api_key' => 'nullable|string',
            'gemini_api_key' => 'nullable|string',
            'ollama_url' => 'nullable|url',
        ]);

        foreach ($validated as $key => $value) {
            if ($value !== null) {
                AppSetting::set($key, $value);
            }
        }

        return response()->json(['message' => 'API keys configured.']);
    }

    /**
     * POST /api/setup/default-model
     * Step 2: Set default model.
     */
    public function configureDefaultModel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'default_model' => 'required|string',
        ]);

        AppSetting::set('default_model', $validated['default_model']);

        return response()->json(['message' => 'Default model set.']);
    }

    /**
     * POST /api/setup/quick-start
     * Step 3: Create first project + agent in one shot.
     */
    public function quickStart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_name' => 'required|string|max:255',
            'agent_name' => 'required|string|max:255',
            'agent_role' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        $project = Project::create([
            'name' => $validated['project_name'],
            'path' => null,
            'organization_id' => $user->current_organization_id,
        ]);

        $agent = Agent::create([
            'name' => $validated['agent_name'],
            'slug' => Str::slug($validated['agent_name']),
            'role' => $validated['agent_role'] ?? 'assistant',
            'model' => AppSetting::get('default_model', 'claude-sonnet-4-6'),
            'base_instructions' => "You are {$validated['agent_name']}, a helpful AI assistant.",
        ]);

        // Attach agent to project
        $project->agents()->attach($agent->id, ['is_enabled' => true]);

        return response()->json([
            'project' => ['id' => $project->id, 'name' => $project->name],
            'agent' => ['id' => $agent->id, 'name' => $agent->name],
        ], 201);
    }

    /**
     * POST /api/setup/complete
     * Mark setup as finished.
     */
    public function complete(): JsonResponse
    {
        AppSetting::set('setup_completed', true);
        AppSetting::set('setup_completed_at', now()->toIso8601String());

        return response()->json(['message' => 'Setup completed.']);
    }
}
