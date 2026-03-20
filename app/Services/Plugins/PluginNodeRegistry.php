<?php

namespace App\Services\Plugins;

use App\Models\Plugin;

class PluginNodeRegistry
{
    /**
     * Get custom node type definitions from all enabled 'node' type plugins for an organization.
     *
     * Each node defines: type, label, icon, configSchema, inputPorts[], outputPorts[].
     *
     * @return array<int, array{
     *     type: string,
     *     label: string,
     *     icon: string|null,
     *     config_schema: array|null,
     *     input_ports: array,
     *     output_ports: array,
     *     plugin_slug: string,
     *     plugin_name: string,
     *     plugin_id: int,
     * }>
     */
    public function getCustomNodes(int $organizationId): array
    {
        $plugins = Plugin::enabled()
            ->where('organization_id', $organizationId)
            ->where(function ($q) {
                $q->where('type', 'node')
                    ->orWhere('type', 'composite');
            })
            ->get();

        $nodes = [];

        foreach ($plugins as $plugin) {
            $manifestNodes = $plugin->manifest['nodes'] ?? [];

            foreach ($manifestNodes as $nodeDef) {
                $nodes[] = [
                    'type' => $nodeDef['type'],
                    'label' => $nodeDef['label'],
                    'icon' => $nodeDef['icon'] ?? null,
                    'config_schema' => $nodeDef['config_schema'] ?? null,
                    'input_ports' => $nodeDef['input_ports'] ?? [],
                    'output_ports' => $nodeDef['output_ports'] ?? [],
                    'plugin_slug' => $plugin->slug,
                    'plugin_name' => $plugin->name,
                    'plugin_id' => $plugin->id,
                ];
            }
        }

        return $nodes;
    }
}
