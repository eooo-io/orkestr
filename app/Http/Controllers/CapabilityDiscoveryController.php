<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\CapabilitySuggestionDismissal;
use App\Services\CapabilityDiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CapabilityDiscoveryController extends Controller
{
    public function __construct(
        protected CapabilityDiscoveryService $discovery,
    ) {}

    public function index(Agent $agent): JsonResponse
    {
        return response()->json([
            'data' => $this->discovery->suggestFor($agent, Auth::id()),
        ]);
    }

    public function dismiss(Request $request, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'suggestion_key' => 'required|string|max:200',
            'expires_in_days' => 'nullable|integer|min:1|max:365',
        ]);

        $expires = isset($validated['expires_in_days'])
            ? now()->addDays((int) $validated['expires_in_days'])
            : now()->addDays(30);

        CapabilitySuggestionDismissal::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'agent_id' => $agent->id,
                'suggestion_key' => $validated['suggestion_key'],
            ],
            ['expires_at' => $expires],
        );

        return response()->json(['message' => 'Suggestion dismissed.']);
    }
}
