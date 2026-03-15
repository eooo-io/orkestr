<?php

namespace App\Http\Controllers;

use App\Models\CustomEndpoint;
use App\Services\LLM\ModelHealthCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomEndpointController extends Controller
{
    public function index(): JsonResponse
    {
        $org = app('current_organization');
        $endpoints = CustomEndpoint::where('organization_id', $org?->id)
            ->orWhereNull('organization_id')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $endpoints]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'base_url' => 'required|url|max:500',
            'api_key' => 'nullable|string|max:500',
            'models' => 'nullable|array',
            'models.*' => 'string|max:255',
        ]);

        $org = app('current_organization');
        $validated['organization_id'] = $org?->id;

        $endpoint = CustomEndpoint::create($validated);

        return response()->json(['data' => $endpoint], 201);
    }

    public function show(CustomEndpoint $customEndpoint): JsonResponse
    {
        return response()->json(['data' => $customEndpoint]);
    }

    public function update(Request $request, CustomEndpoint $customEndpoint): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'base_url' => 'sometimes|url|max:500',
            'api_key' => 'nullable|string|max:500',
            'models' => 'nullable|array',
            'models.*' => 'string|max:255',
            'enabled' => 'sometimes|boolean',
        ]);

        $customEndpoint->update($validated);

        return response()->json(['data' => $customEndpoint->fresh()]);
    }

    public function destroy(CustomEndpoint $customEndpoint): JsonResponse
    {
        $customEndpoint->delete();

        return response()->json(null, 204);
    }

    public function healthCheck(CustomEndpoint $customEndpoint, ModelHealthCheckService $healthService): JsonResponse
    {
        $result = $healthService->checkCustomEndpoint($customEndpoint);

        return response()->json(['data' => $result]);
    }

    public function discoverModels(CustomEndpoint $customEndpoint): JsonResponse
    {
        $provider = new \App\Services\LLM\OpenAICompatibleProvider(
            baseUrl: $customEndpoint->base_url,
            apiKey: $customEndpoint->api_key,
            providerName: $customEndpoint->name,
        );

        $models = $provider->models();

        if (! empty($models)) {
            $customEndpoint->update(['models' => $models]);
        }

        return response()->json(['data' => ['models' => $models]]);
    }
}
