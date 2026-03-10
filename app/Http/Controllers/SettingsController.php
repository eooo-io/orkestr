<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Rules\SafeProjectPath;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'anthropic_api_key_set' => ! empty(AppSetting::get('anthropic_api_key')),
            'default_model' => AppSetting::get('default_model', 'claude-sonnet-4-20250514'),
            'allowed_project_paths' => SafeProjectPath::getAllowedBases(),
        ]);
    }
}
