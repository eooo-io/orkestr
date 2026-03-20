<?php

namespace App\Services\Plugins;

use App\Models\Plugin;
use App\Models\PluginHook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PluginHookDispatcher
{
    /**
     * All recognized hook points in the system.
     */
    public const HOOK_POINTS = [
        'before_execution' => 'Fires before an agent execution starts. Can modify context or abort.',
        'after_execution' => 'Fires after an agent execution completes.',
        'before_sync' => 'Fires before provider sync. Can modify the skill output.',
        'after_sync' => 'Fires after provider sync completes.',
        'on_skill_save' => 'Fires when a skill is saved.',
        'on_skill_delete' => 'Fires when a skill is deleted.',
        'on_agent_start' => 'Fires when an agent process starts.',
        'on_agent_stop' => 'Fires when an agent process stops.',
        'before_tool_call' => 'Fires before a tool is invoked. Can modify input or abort.',
        'after_tool_call' => 'Fires after a tool invocation returns.',
        'on_error' => 'Fires when an error occurs during execution.',
        'on_artifact_create' => 'Fires when a new artifact is created.',
        'on_workflow_step' => 'Fires when a workflow step executes.',
        'on_plugin_install' => 'Fires when a plugin is installed.',
        'on_plugin_uninstall' => 'Fires when a plugin is uninstalled.',
    ];

    /**
     * Hook prefixes that run synchronously and can modify/abort context.
     */
    private const SYNCHRONOUS_PREFIXES = ['before_'];

    /**
     * Dispatch a hook event to all registered handlers.
     *
     * For `before_*` hooks: synchronous, handlers can modify context or set abort.
     * For all other hooks: fire-and-forget (logged, but exceptions don't propagate).
     *
     * @return array{results: array, aborted: bool, context: array}
     */
    public function dispatch(string $hookName, array $context = []): array
    {
        $isSynchronous = $this->isSynchronousHook($hookName);

        $hooks = PluginHook::active()
            ->where('hook_name', $hookName)
            ->whereHas('plugin', fn ($q) => $q->where('enabled', true))
            ->with('plugin')
            ->orderBy('priority')
            ->get();

        $results = [];
        $aborted = false;

        foreach ($hooks as $hook) {
            try {
                $result = $this->executeHook($hook, $context);
                $results[] = [
                    'plugin_id' => $hook->plugin_id,
                    'plugin_name' => $hook->plugin->name,
                    'hook_name' => $hookName,
                    'success' => true,
                    'result' => $result,
                ];

                if ($isSynchronous && is_array($result)) {
                    // Allow hooks to modify context
                    if (isset($result['context']) && is_array($result['context'])) {
                        $context = array_merge($context, $result['context']);
                    }

                    // Allow hooks to abort execution
                    if (! empty($result['abort'])) {
                        $aborted = true;
                        break;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Plugin hook execution failed", [
                    'plugin_id' => $hook->plugin_id,
                    'hook_name' => $hookName,
                    'error' => $e->getMessage(),
                ]);

                $results[] = [
                    'plugin_id' => $hook->plugin_id,
                    'plugin_name' => $hook->plugin->name,
                    'hook_name' => $hookName,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];

                // For synchronous hooks, a failure does NOT abort — only explicit abort does
            }
        }

        return [
            'results' => $results,
            'aborted' => $aborted,
            'context' => $context,
        ];
    }

    /**
     * Get all available hook points.
     */
    public function getHookPoints(): array
    {
        return collect(self::HOOK_POINTS)->map(fn ($desc, $name) => [
            'name' => $name,
            'description' => $desc,
            'synchronous' => $this->isSynchronousHook($name),
        ])->values()->toArray();
    }

    /**
     * Execute a single hook handler.
     */
    protected function executeHook(PluginHook $hook, array $context): mixed
    {
        $plugin = $hook->plugin;
        $entryPoint = $hook->handler;

        // If the handler looks like a URL, POST to it
        if (filter_var($entryPoint, FILTER_VALIDATE_URL)) {
            return $this->executeHttpHook($entryPoint, $hook, $context, $plugin);
        }

        // Otherwise treat as a PHP class
        return $this->executeClassHook($entryPoint, $hook, $context, $plugin);
    }

    /**
     * Execute a hook via HTTP POST.
     */
    protected function executeHttpHook(string $url, PluginHook $hook, array $context, Plugin $plugin): mixed
    {
        $response = Http::timeout(10)
            ->withHeaders([
                'X-Plugin-Id' => (string) $plugin->uuid,
                'X-Hook-Name' => $hook->hook_name,
                'Content-Type' => 'application/json',
            ])
            ->post($url, [
                'hook_name' => $hook->hook_name,
                'context' => $context,
                'plugin_config' => $plugin->config ?? [],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                "HTTP hook returned status {$response->status()}: {$response->body()}"
            );
        }

        return $response->json();
    }

    /**
     * Execute a hook via a PHP class.
     */
    protected function executeClassHook(string $className, PluginHook $hook, array $context, Plugin $plugin): mixed
    {
        if (! class_exists($className)) {
            throw new \RuntimeException("Plugin handler class not found: {$className}");
        }

        $instance = app($className);

        if (! method_exists($instance, 'handle')) {
            throw new \RuntimeException("Plugin handler class {$className} must implement a handle() method");
        }

        return $instance->handle($hook->hook_name, $context, $plugin->config ?? []);
    }

    /**
     * Check if a hook name is synchronous (before_* pattern).
     */
    protected function isSynchronousHook(string $hookName): bool
    {
        foreach (self::SYNCHRONOUS_PREFIXES as $prefix) {
            if (str_starts_with($hookName, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
