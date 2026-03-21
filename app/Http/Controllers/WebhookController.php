<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Webhook;
use App\Services\WebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        $webhooks = $project->webhooks()
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $webhooks]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'event' => 'required|string|in:skill.created,skill.updated,skill.deleted,project.synced',
            'url' => 'required|url|max:500',
            'secret' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $webhook = $project->webhooks()->create([
            'event' => $validated['event'],
            'url' => $validated['url'],
            'secret' => $validated['secret'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['data' => $webhook], 201);
    }

    public function update(Request $request, Webhook $webhook): JsonResponse
    {
        $validated = $request->validate([
            'event' => 'sometimes|required|string|in:skill.created,skill.updated,skill.deleted,project.synced',
            'url' => 'sometimes|required|url|max:500',
            'secret' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $webhook->update($validated);

        return response()->json(['data' => $webhook->fresh()]);
    }

    public function destroy(Webhook $webhook): JsonResponse
    {
        $webhook->delete();

        return response()->json(['message' => 'Webhook deleted']);
    }

    public function deliveries(Webhook $webhook): JsonResponse
    {
        $deliveries = $webhook->deliveries()
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json(['data' => $deliveries]);
    }

    public function test(Webhook $webhook, WebhookDispatcher $dispatcher): JsonResponse
    {
        $payload = [
            'test' => true,
            'project_id' => $webhook->project_id,
            'message' => 'This is a test webhook delivery from Orkestr.',
        ];

        $dispatcher->deliverWebhook($webhook, $webhook->event, $payload);

        return response()->json(['message' => 'Test webhook sent']);
    }
}
