<?php

namespace App\Services\Plugins;

use App\Models\Plugin;
use App\Models\Project;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PluginToolProvider
{
    /**
     * Get tool definitions from all enabled 'tool' type plugins for the project's organization.
     *
     * @return array<int, array{name: string, description: string, parameters: array, plugin_slug: string, plugin_name: string}>
     */
    public function getTools(int $projectId): array
    {
        $project = Project::find($projectId);

        if (! $project || ! $project->organization_id) {
            return [];
        }

        $plugins = Plugin::enabled()
            ->where('organization_id', $project->organization_id)
            ->where(function ($q) {
                $q->where('type', 'tool')
                    ->orWhere('type', 'composite');
            })
            ->get();

        $tools = [];

        foreach ($plugins as $plugin) {
            $manifestTools = $plugin->manifest['tools'] ?? [];

            foreach ($manifestTools as $tool) {
                $tools[] = [
                    'name' => $tool['name'],
                    'description' => $tool['description'] ?? '',
                    'parameters' => $tool['parameters'] ?? [],
                    'plugin_slug' => $plugin->slug,
                    'plugin_name' => $plugin->name,
                    'plugin_id' => $plugin->id,
                ];
            }
        }

        return $tools;
    }

    /**
     * Execute a tool provided by a plugin.
     *
     * @return array The tool execution response
     */
    public function executeTool(string $pluginSlug, string $toolName, array $input): array
    {
        $plugin = Plugin::enabled()
            ->where('slug', $pluginSlug)
            ->first();

        if (! $plugin) {
            throw new \RuntimeException("Plugin not found or disabled: {$pluginSlug}");
        }

        // Verify the tool exists in the manifest
        $manifestTools = $plugin->manifest['tools'] ?? [];
        $toolDef = collect($manifestTools)->firstWhere('name', $toolName);

        if (! $toolDef) {
            throw new \RuntimeException("Tool '{$toolName}' not found in plugin '{$pluginSlug}'");
        }

        $entryPoint = $plugin->entry_point;

        // If entry_point is a URL, POST the tool invocation
        if (filter_var($entryPoint, FILTER_VALIDATE_URL)) {
            return $this->executeToolViaHttp($entryPoint, $plugin, $toolName, $input);
        }

        // If entry_point is a PHP class, instantiate and call executeTool()
        return $this->executeToolViaClass($entryPoint, $plugin, $toolName, $input);
    }

    /**
     * Execute a tool via HTTP POST to the plugin's entry point.
     */
    protected function executeToolViaHttp(string $url, Plugin $plugin, string $toolName, array $input): array
    {
        $response = Http::timeout(30)
            ->withHeaders([
                'X-Plugin-Id' => (string) $plugin->uuid,
                'X-Tool-Name' => $toolName,
                'Content-Type' => 'application/json',
            ])
            ->post($url, [
                'action' => 'execute_tool',
                'tool' => $toolName,
                'input' => $input,
                'plugin_config' => $plugin->config ?? [],
            ]);

        if ($response->failed()) {
            Log::error("Plugin tool execution failed via HTTP", [
                'plugin' => $plugin->slug,
                'tool' => $toolName,
                'status' => $response->status(),
            ]);

            throw new \RuntimeException(
                "Plugin tool execution failed (HTTP {$response->status()}): {$response->body()}"
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Execute a tool via a PHP class.
     */
    protected function executeToolViaClass(string $className, Plugin $plugin, string $toolName, array $input): array
    {
        if (! class_exists($className)) {
            throw new \RuntimeException("Plugin class not found: {$className}");
        }

        $instance = app($className);

        if (! method_exists($instance, 'executeTool')) {
            throw new \RuntimeException(
                "Plugin class {$className} must implement executeTool(string \$toolName, array \$input, array \$config): array"
            );
        }

        $result = $instance->executeTool($toolName, $input, $plugin->config ?? []);

        if (! is_array($result)) {
            return ['output' => $result];
        }

        return $result;
    }
}
