<?php

namespace App\Services\Plugins;

use App\Models\Plugin;
use App\Models\PluginHook;
use Illuminate\Support\Str;

class PluginManager
{
    /**
     * Required top-level manifest keys.
     */
    private const REQUIRED_MANIFEST_KEYS = ['name', 'version', 'type', 'entry_point'];

    /**
     * Allowed plugin types.
     */
    private const VALID_TYPES = ['tool', 'node', 'panel', 'provider', 'composite'];

    /**
     * Install a plugin from a manifest definition.
     */
    public function install(array $manifest, int $organizationId): Plugin
    {
        $errors = $this->validateManifest($manifest);

        if (! empty($errors)) {
            throw new \InvalidArgumentException(
                'Invalid plugin manifest: ' . implode('; ', $errors)
            );
        }

        $plugin = Plugin::create([
            'organization_id' => $organizationId,
            'name' => $manifest['name'],
            'slug' => Str::slug($manifest['name']),
            'description' => $manifest['description'] ?? null,
            'version' => $manifest['version'],
            'author' => $manifest['author'] ?? null,
            'type' => $manifest['type'],
            'manifest' => $manifest,
            'entry_point' => $manifest['entry_point'],
            'config' => $manifest['default_config'] ?? null,
            'enabled' => true,
            'installed_at' => now(),
        ]);

        // Create hook registrations from manifest
        $hooks = $manifest['hooks'] ?? [];
        foreach ($hooks as $hookDef) {
            PluginHook::create([
                'plugin_id' => $plugin->id,
                'hook_name' => $hookDef['hook_name'],
                'handler' => $hookDef['handler'] ?? $manifest['entry_point'],
                'priority' => $hookDef['priority'] ?? 0,
                'enabled' => true,
            ]);
        }

        $plugin->load('hooks');

        return $plugin;
    }

    /**
     * Enable a plugin and all its hooks.
     */
    public function enable(Plugin $plugin): Plugin
    {
        $plugin->update(['enabled' => true]);
        $plugin->hooks()->update(['enabled' => true]);

        return $plugin->fresh('hooks');
    }

    /**
     * Disable a plugin and all its hooks.
     */
    public function disable(Plugin $plugin): Plugin
    {
        $plugin->update(['enabled' => false]);
        $plugin->hooks()->update(['enabled' => false]);

        return $plugin->fresh('hooks');
    }

    /**
     * Uninstall a plugin — removes all hooks and the plugin record.
     */
    public function uninstall(Plugin $plugin): void
    {
        $plugin->hooks()->delete();
        $plugin->delete();
    }

    /**
     * Update user-facing configuration for a plugin.
     * Validates values against the manifest's configSchema if present.
     */
    public function updateConfig(Plugin $plugin, array $config): Plugin
    {
        $schema = $plugin->manifest['config_schema'] ?? null;

        if ($schema) {
            $errors = $this->validateConfigAgainstSchema($config, $schema);

            if (! empty($errors)) {
                throw new \InvalidArgumentException(
                    'Invalid config: ' . implode('; ', $errors)
                );
            }
        }

        $plugin->update(['config' => $config]);

        return $plugin->fresh();
    }

    /**
     * List enabled plugins, optionally filtered by type.
     */
    public function getEnabled(?string $type = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = Plugin::enabled()->with('hooks');

        if ($type !== null) {
            $query->ofType($type);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Validate a manifest structure. Returns an array of error strings (empty = valid).
     */
    public function validateManifest(array $manifest): array
    {
        $errors = [];

        foreach (self::REQUIRED_MANIFEST_KEYS as $key) {
            if (empty($manifest[$key])) {
                $errors[] = "Missing required field: {$key}";
            }
        }

        if (isset($manifest['type']) && ! in_array($manifest['type'], self::VALID_TYPES, true)) {
            $errors[] = 'Invalid type: ' . $manifest['type'] . '. Must be one of: ' . implode(', ', self::VALID_TYPES);
        }

        if (isset($manifest['version']) && ! preg_match('/^\d+\.\d+(\.\d+)?(-[\w.]+)?$/', $manifest['version'])) {
            $errors[] = 'Version must follow semver format (e.g., 1.0.0)';
        }

        if (isset($manifest['hooks'])) {
            if (! is_array($manifest['hooks'])) {
                $errors[] = 'hooks must be an array';
            } else {
                foreach ($manifest['hooks'] as $i => $hook) {
                    if (empty($hook['hook_name'])) {
                        $errors[] = "hooks[{$i}] missing hook_name";
                    }
                    if (isset($hook['priority']) && ! is_int($hook['priority'])) {
                        $errors[] = "hooks[{$i}].priority must be an integer";
                    }
                }
            }
        }

        if (isset($manifest['config_schema'])) {
            if (! is_array($manifest['config_schema'])) {
                $errors[] = 'config_schema must be an object';
            }
        }

        if (isset($manifest['tools'])) {
            if (! is_array($manifest['tools'])) {
                $errors[] = 'tools must be an array';
            } else {
                foreach ($manifest['tools'] as $i => $tool) {
                    if (empty($tool['name'])) {
                        $errors[] = "tools[{$i}] missing name";
                    }
                }
            }
        }

        if (isset($manifest['nodes'])) {
            if (! is_array($manifest['nodes'])) {
                $errors[] = 'nodes must be an array';
            } else {
                foreach ($manifest['nodes'] as $i => $node) {
                    if (empty($node['type'])) {
                        $errors[] = "nodes[{$i}] missing type";
                    }
                    if (empty($node['label'])) {
                        $errors[] = "nodes[{$i}] missing label";
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Validate config values against a JSON-schema-like config_schema.
     */
    protected function validateConfigAgainstSchema(array $config, array $schema): array
    {
        $errors = [];
        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];

        // Check required fields
        foreach ($required as $field) {
            if (! array_key_exists($field, $config)) {
                $errors[] = "Missing required config field: {$field}";
            }
        }

        // Check types
        foreach ($config as $key => $value) {
            if (! isset($properties[$key])) {
                continue; // Allow extra fields
            }

            $prop = $properties[$key];
            $expectedType = $prop['type'] ?? null;

            if ($expectedType === null) {
                continue;
            }

            $valid = match ($expectedType) {
                'string' => is_string($value),
                'number', 'integer' => is_numeric($value),
                'boolean' => is_bool($value),
                'array' => is_array($value),
                'object' => is_array($value) && ! array_is_list($value),
                default => true,
            };

            if (! $valid) {
                $errors[] = "Config field '{$key}' must be of type {$expectedType}";
            }

            // Check enum constraint
            if (isset($prop['enum']) && ! in_array($value, $prop['enum'], true)) {
                $errors[] = "Config field '{$key}' must be one of: " . implode(', ', $prop['enum']);
            }
        }

        return $errors;
    }
}
