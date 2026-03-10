<?php

namespace App\Http\Controllers;

use App\Jobs\ProjectScanJob;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InboundWebhookController extends Controller
{
    public function github(Request $request, Project $project): JsonResponse
    {
        // Validate GitHub webhook signature if a secret is configured
        $configuredSecret = $project->webhooks()
            ->where('event', 'github.push')
            ->value('secret');

        if ($configuredSecret) {
            $signature = $request->header('X-Hub-Signature-256');

            if (! $signature) {
                return response()->json(['message' => 'Missing signature'], 403);
            }

            $expectedSignature = 'sha256=' . hash_hmac('sha256', $request->getContent(), $configuredSecret);

            if (! hash_equals($expectedSignature, $signature)) {
                return response()->json(['message' => 'Invalid signature'], 403);
            }
        }

        // Only trigger scan for push events (default GitHub webhook event)
        $event = $request->header('X-GitHub-Event', 'push');

        if ($event === 'push') {
            ProjectScanJob::dispatch($project);

            return response()->json(['message' => 'Scan queued']);
        }

        return response()->json(['message' => 'Event ignored']);
    }
}
