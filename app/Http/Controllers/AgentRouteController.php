<?php

namespace App\Http\Controllers;

use App\Services\AgentRoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentRouteController extends Controller
{
    public function __construct(
        protected AgentRoutingService $routing,
    ) {}

    public function route(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'required|string|min:3|max:500',
            'project_id' => 'nullable|integer|exists:projects,id',
        ]);

        $rankings = $this->routing->rank(
            $validated['question'],
            $validated['project_id'] ?? null,
        );

        return response()->json(['data' => $rankings]);
    }
}
