<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Rules\SafeProjectPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'anthropic_api_key_set' => ! empty(AppSetting::get('anthropic_api_key')),
            'openai_api_key_set' => ! empty(AppSetting::get('openai_api_key')),
            'gemini_api_key_set' => ! empty(AppSetting::get('gemini_api_key')),
            'ollama_url' => AppSetting::get('ollama_url', 'http://localhost:11434'),
            'default_model' => AppSetting::get('default_model', 'claude-sonnet-4-20250514'),
            'allowed_project_paths' => SafeProjectPath::getAllowedBases(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'anthropic_api_key' => 'nullable|string|max:500',
            'openai_api_key' => 'nullable|string|max:500',
            'gemini_api_key' => 'nullable|string|max:500',
            'ollama_url' => 'nullable|string|max:500',
            'default_model' => 'nullable|string|max:100',
        ]);

        foreach ($validated as $key => $value) {
            if ($value === null) {
                continue;
            }
            // Don't store empty strings for API keys — treat as "unset"
            if (str_ends_with($key, '_api_key') && $value === '') {
                AppSetting::where('key', $key)->delete();
                continue;
            }
            AppSetting::set($key, $value);
        }

        return response()->json(['message' => 'Settings updated.']);
    }
}
