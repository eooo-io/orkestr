<?php

namespace App\Services\LLM;

use App\Models\AppSetting;
use App\Models\CustomEndpoint;

/**
 * Discovers and lists locally available models from Ollama and custom endpoints.
 */
class LocalModelBrowserService
{
    /**
     * Get all locally available models with metadata.
     */
    public function discover(): array
    {
        $models = [];

        // Ollama models
        $ollamaModels = $this->discoverOllama();
        foreach ($ollamaModels as $model) {
            $models[] = array_merge($model, ['source' => 'ollama']);
        }

        // Custom local endpoints
        $customModels = $this->discoverCustomEndpoints();
        foreach ($customModels as $model) {
            $models[] = array_merge($model, ['source' => 'custom']);
        }

        return $models;
    }

    /**
     * Discover models from Ollama with detailed info.
     */
    public function discoverOllama(): array
    {
        $baseUrl = rtrim(
            AppSetting::get('ollama_url') ?: env('OLLAMA_URL', 'http://localhost:11434'),
            '/',
        );

        try {
            $ch = curl_init("{$baseUrl}/api/tags");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || ! $response) {
                return [];
            }

            $data = json_decode($response, true);
            $models = [];

            foreach ($data['models'] ?? [] as $model) {
                $name = $model['name'] ?? $model['model'] ?? '';
                if (empty($name)) {
                    continue;
                }

                $models[] = [
                    'id' => $name,
                    'name' => $name,
                    'size_bytes' => $model['size'] ?? null,
                    'size_formatted' => isset($model['size']) ? $this->formatBytes($model['size']) : null,
                    'quantization' => $model['details']['quantization_level'] ?? null,
                    'family' => $model['details']['family'] ?? null,
                    'parameter_size' => $model['details']['parameter_size'] ?? null,
                    'format' => $model['details']['format'] ?? null,
                    'modified_at' => $model['modified_at'] ?? null,
                ];
            }

            return $models;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Discover models from custom local endpoints.
     */
    public function discoverCustomEndpoints(): array
    {
        $endpoints = CustomEndpoint::where('enabled', true)->get();
        $models = [];

        foreach ($endpoints as $endpoint) {
            $parsed = parse_url($endpoint->base_url);
            $host = $parsed['host'] ?? '';
            $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1']) || str_ends_with($host, '.local');

            if (! $isLocal) {
                continue;
            }

            foreach ($endpoint->models ?? [] as $modelId) {
                $models[] = [
                    'id' => "custom:{$endpoint->slug}:{$modelId}",
                    'name' => $modelId,
                    'endpoint_name' => $endpoint->name,
                    'endpoint_slug' => $endpoint->slug,
                    'size_bytes' => null,
                    'size_formatted' => null,
                    'quantization' => null,
                    'family' => null,
                    'parameter_size' => null,
                    'format' => null,
                    'modified_at' => null,
                ];
            }
        }

        return $models;
    }

    /**
     * Get detailed info about a specific Ollama model.
     */
    public function showOllamaModel(string $modelName): ?array
    {
        $baseUrl = rtrim(
            AppSetting::get('ollama_url') ?: env('OLLAMA_URL', 'http://localhost:11434'),
            '/',
        );

        try {
            $ch = curl_init("{$baseUrl}/api/show");
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode(['name' => $modelName]),
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || ! $response) {
                return null;
            }

            $data = json_decode($response, true);

            return [
                'name' => $modelName,
                'license' => $data['license'] ?? null,
                'modelfile' => $data['modelfile'] ?? null,
                'parameters' => $data['parameters'] ?? null,
                'template' => $data['template'] ?? null,
                'details' => $data['details'] ?? [],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        return round($bytes / 1024, 1) . ' KB';
    }
}
