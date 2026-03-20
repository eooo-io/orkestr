<?php

namespace App\Http\Controllers;

use App\Models\Plugin;
use App\Services\Plugins\PluginHookDispatcher;
use App\Services\Plugins\PluginManager;
use App\Services\Plugins\PluginNodeRegistry;
use App\Services\Plugins\PluginToolProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PluginController extends Controller
{
    public function __construct(
        protected PluginManager $manager,
        protected PluginHookDispatcher $dispatcher,
        protected PluginToolProvider $toolProvider,
        protected PluginNodeRegistry $nodeRegistry,
    ) {}

    /**
     * GET /api/plugins
     * List installed plugins for current organization, filterable by type.
     */
    public function index(Request $request): JsonResponse
    {
        $org = app('current_organization');

        if (! $org) {
            return response()->json(['message' => 'Organization not resolved'], 403);
        }

        $query = Plugin::where('organization_id', $org->id)
            ->with('hooks')
            ->orderBy('name');

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        $plugins = $query->get()->map(fn (Plugin $p) => $this->formatPlugin($p));

        return response()->json(['data' => $plugins]);
    }

    /**
     * POST /api/plugins
     * Install a plugin from manifest JSON.
     */
    public function store(Request $request): JsonResponse
    {
        $org = app('current_organization');

        if (! $org) {
            return response()->json(['message' => 'Organization not resolved'], 403);
        }

        $validated = $request->validate([
            'manifest' => 'required|array',
            'manifest.name' => 'required|string|max:255',
            'manifest.version' => 'required|string|max:50',
            'manifest.type' => 'required|string|in:tool,node,panel,provider,composite',
            'manifest.entry_point' => 'required|string|max:500',
            'manifest.description' => 'nullable|string|max:2000',
            'manifest.author' => 'nullable|string|max:255',
            'manifest.hooks' => 'nullable|array',
            'manifest.hooks.*.hook_name' => 'required|string',
            'manifest.hooks.*.handler' => 'nullable|string',
            'manifest.hooks.*.priority' => 'nullable|integer',
            'manifest.tools' => 'nullable|array',
            'manifest.tools.*.name' => 'required|string',
            'manifest.tools.*.description' => 'nullable|string',
            'manifest.tools.*.parameters' => 'nullable|array',
            'manifest.nodes' => 'nullable|array',
            'manifest.config_schema' => 'nullable|array',
            'manifest.default_config' => 'nullable|array',
            'manifest.capabilities' => 'nullable|array',
        ]);

        // Check for duplicate slug
        $slug = \Illuminate\Support\Str::slug($validated['manifest']['name']);
        $exists = Plugin::where('organization_id', $org->id)
            ->where('slug', $slug)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => "A plugin with slug '{$slug}' is already installed.",
            ], 422);
        }

        try {
            $plugin = $this->manager->install($validated['manifest'], $org->id);

            // Dispatch install hook
            $this->dispatcher->dispatch('on_plugin_install', [
                'plugin_id' => $plugin->id,
                'plugin_name' => $plugin->name,
            ]);

            return response()->json(['data' => $this->formatPlugin($plugin)], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/plugins/{plugin}
     * Show plugin detail with hooks.
     */
    public function show(Plugin $plugin): JsonResponse
    {
        $plugin->load('hooks');

        return response()->json(['data' => $this->formatPlugin($plugin)]);
    }

    /**
     * PUT /api/plugins/{plugin}
     * Update plugin configuration.
     */
    public function update(Request $request, Plugin $plugin): JsonResponse
    {
        $validated = $request->validate([
            'config' => 'required|array',
        ]);

        try {
            $plugin = $this->manager->updateConfig($plugin, $validated['config']);
            $plugin->load('hooks');

            return response()->json(['data' => $this->formatPlugin($plugin)]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/plugins/{plugin}/enable
     */
    public function enable(Plugin $plugin): JsonResponse
    {
        $plugin = $this->manager->enable($plugin);

        return response()->json(['data' => $this->formatPlugin($plugin)]);
    }

    /**
     * POST /api/plugins/{plugin}/disable
     */
    public function disable(Plugin $plugin): JsonResponse
    {
        $plugin = $this->manager->disable($plugin);

        return response()->json(['data' => $this->formatPlugin($plugin)]);
    }

    /**
     * DELETE /api/plugins/{plugin}
     */
    public function destroy(Plugin $plugin): JsonResponse
    {
        $this->dispatcher->dispatch('on_plugin_uninstall', [
            'plugin_id' => $plugin->id,
            'plugin_name' => $plugin->name,
        ]);

        $this->manager->uninstall($plugin);

        return response()->json(['message' => 'Plugin uninstalled']);
    }

    /**
     * GET /api/plugins/hooks
     * List all available hook points.
     */
    public function hooks(): JsonResponse
    {
        return response()->json(['data' => $this->dispatcher->getHookPoints()]);
    }

    /**
     * GET /api/plugins/tools
     * List plugin-provided tools for a project.
     */
    public function availableTools(Request $request): JsonResponse
    {
        $projectId = $request->query('project_id');

        if (! $projectId) {
            return response()->json(['message' => 'project_id query parameter required'], 422);
        }

        $tools = $this->toolProvider->getTools((int) $projectId);

        return response()->json(['data' => $tools]);
    }

    /**
     * GET /api/plugins/nodes
     * List plugin-provided custom nodes.
     */
    public function availableNodes(): JsonResponse
    {
        $org = app('current_organization');

        if (! $org) {
            return response()->json(['message' => 'Organization not resolved'], 403);
        }

        $nodes = $this->nodeRegistry->getCustomNodes($org->id);

        return response()->json(['data' => $nodes]);
    }

    /**
     * Format a plugin for JSON response.
     */
    protected function formatPlugin(Plugin $plugin): array
    {
        return [
            'id' => $plugin->id,
            'uuid' => $plugin->uuid,
            'name' => $plugin->name,
            'slug' => $plugin->slug,
            'description' => $plugin->description,
            'version' => $plugin->version,
            'author' => $plugin->author,
            'type' => $plugin->type,
            'manifest' => $plugin->manifest,
            'entry_point' => $plugin->entry_point,
            'config' => $plugin->config,
            'enabled' => $plugin->enabled,
            'installed_at' => $plugin->installed_at?->toISOString(),
            'created_at' => $plugin->created_at?->toISOString(),
            'updated_at' => $plugin->updated_at?->toISOString(),
            'hooks' => ($plugin->relationLoaded('hooks'))
                ? $plugin->hooks->map(fn ($h) => [
                    'id' => $h->id,
                    'hook_name' => $h->hook_name,
                    'handler' => $h->handler,
                    'priority' => $h->priority,
                    'enabled' => $h->enabled,
                ])->values()->toArray()
                : [],
        ];
    }
}
