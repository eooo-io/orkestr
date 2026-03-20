<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\MarketplaceAgent;
use App\Models\Project;
use App\Services\MarketplaceAgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceAgentController extends Controller
{
    public function __construct(
        protected MarketplaceAgentService $service,
    ) {}

    /**
     * List marketplace agent templates (paginated, filterable, sortable).
     */
    public function index(Request $request): JsonResponse
    {
        $query = MarketplaceAgent::query();

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($tags = $request->input('tags')) {
            $tagNames = is_array($tags) ? $tags : explode(',', $tags);
            foreach ($tagNames as $tag) {
                $query->whereJsonContains('tags', trim($tag));
            }
        }

        if ($q = $request->input('q')) {
            $query->search($q);
        }

        $sort = $request->input('sort', 'newest');
        $query = match ($sort) {
            'popular' => $query->orderByDesc('downloads'),
            'top-rated' => $query->orderByRaw('(upvotes - downvotes) DESC'),
            default => $query->orderByDesc('created_at'),
        };

        $paginated = $query->paginate(20);

        return response()->json($paginated);
    }

    /**
     * Show a single marketplace agent listing.
     */
    public function show(MarketplaceAgent $marketplaceAgent): JsonResponse
    {
        $marketplaceAgent->load('publisher');

        return response()->json(['data' => $marketplaceAgent]);
    }

    /**
     * Publish an agent to the marketplace.
     */
    public function publish(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => 'required|integer|exists:agents,id',
            'project_id' => 'required|integer|exists:projects,id',
            'description' => 'nullable|string|max:5000',
            'category' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100',
            'author' => 'required|string|max:255',
            'author_url' => 'nullable|url|max:500',
            'version' => 'nullable|string|max:50',
            'readme' => 'nullable|string|max:50000',
            'screenshots' => 'nullable|array',
            'screenshots.*' => 'string|url',
        ]);

        $agent = Agent::findOrFail($validated['agent_id']);
        $project = Project::findOrFail($validated['project_id']);

        $meta = [
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'tags' => $validated['tags'] ?? [],
            'author' => $validated['author'],
            'author_url' => $validated['author_url'] ?? null,
            'version' => $validated['version'] ?? '1.0.0',
            'readme' => $validated['readme'] ?? null,
            'screenshots' => $validated['screenshots'] ?? null,
            'user_id' => $request->user()?->id,
        ];

        $listing = $this->service->publish($agent, $project, $meta);

        return response()->json(['data' => $listing], 201);
    }

    /**
     * Install a marketplace agent into a project.
     */
    public function install(Request $request, MarketplaceAgent $marketplaceAgent): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|integer|exists:projects,id',
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $agent = $this->service->install($marketplaceAgent, $project, $request->user()?->id);

        return response()->json([
            'data' => $agent,
            'message' => 'Agent template installed successfully.',
        ]);
    }

    /**
     * Vote on a marketplace agent listing.
     */
    public function vote(Request $request, MarketplaceAgent $marketplaceAgent): JsonResponse
    {
        $validated = $request->validate([
            'direction' => 'required|in:up,down',
        ]);

        if ($validated['direction'] === 'up') {
            $marketplaceAgent->increment('upvotes');
        } else {
            $marketplaceAgent->increment('downvotes');
        }

        return response()->json(['data' => $marketplaceAgent->fresh()]);
    }

    /**
     * Preview a marketplace agent listing (structured summary).
     */
    public function preview(MarketplaceAgent $marketplaceAgent): JsonResponse
    {
        return response()->json([
            'data' => $this->service->preview($marketplaceAgent),
        ]);
    }
}
