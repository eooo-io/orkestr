<?php

namespace App\Services;

use Illuminate\Support\Facades\Route;

/**
 * Generates an OpenAPI 3.1 specification from registered Laravel routes.
 */
class OpenApiSpecService
{
    public function generate(): array
    {
        $spec = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => config('app.name', 'Agentis Studio') . ' API',
                'version' => '1.0.0',
                'description' => 'REST API for managing AI skills, agents, projects, and orchestration workflows.',
                'contact' => [
                    'name' => 'eooo.ai',
                    'url' => 'https://eooo.ai',
                ],
            ],
            'servers' => [
                ['url' => config('app.url') . '/api', 'description' => 'Current server'],
            ],
            'security' => [
                ['bearerAuth' => []],
                ['cookieAuth' => []],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'description' => 'API token authentication',
                    ],
                    'cookieAuth' => [
                        'type' => 'apiKey',
                        'in' => 'cookie',
                        'name' => config('session.cookie', 'laravel_session'),
                        'description' => 'Session cookie authentication',
                    ],
                ],
            ],
            'paths' => [],
            'tags' => $this->getTags(),
        ];

        $routes = Route::getRoutes();
        foreach ($routes as $route) {
            $uri = $route->uri();

            // Only include api/ routes
            if (! str_starts_with($uri, 'api/')) {
                continue;
            }

            // Skip internal routes
            if (str_contains($uri, 'sanctum') || str_contains($uri, 'stripe/webhook')) {
                continue;
            }

            $path = '/' . str_replace('api/', '', $uri);
            // Convert Laravel {param} to OpenAPI {param}
            $path = preg_replace('/\{(\w+)\}/', '{$1}', $path);

            $methods = array_filter($route->methods(), fn ($m) => $m !== 'HEAD');

            foreach ($methods as $method) {
                $method = strtolower($method);
                $spec['paths'][$path][$method] = $this->buildOperation($route, $method, $path);
            }
        }

        ksort($spec['paths']);

        return $spec;
    }

    protected function buildOperation($route, string $method, string $path): array
    {
        $tag = $this->guessTag($path);
        $operationId = $this->buildOperationId($route, $method);

        $operation = [
            'tags' => [$tag],
            'operationId' => $operationId,
            'summary' => $this->buildSummary($route, $method),
            'responses' => [
                '200' => ['description' => 'Successful response'],
                '401' => ['description' => 'Unauthenticated'],
                '422' => ['description' => 'Validation error'],
            ],
        ];

        // Extract path parameters
        preg_match_all('/\{(\w+)\}/', $path, $matches);
        if (! empty($matches[1])) {
            $operation['parameters'] = array_map(fn ($param) => [
                'name' => $param,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
            ], $matches[1]);
        }

        if (in_array($method, ['post', 'put', 'patch'])) {
            $operation['requestBody'] = [
                'content' => [
                    'application/json' => [
                        'schema' => ['type' => 'object'],
                    ],
                ],
            ];
        }

        return $operation;
    }

    protected function guessTag(string $path): string
    {
        $segments = explode('/', trim($path, '/'));
        $first = $segments[0] ?? 'general';

        $tagMap = [
            'projects' => 'Projects',
            'skills' => 'Skills',
            'agents' => 'Agents',
            'tags' => 'Tags',
            'search' => 'Search',
            'library' => 'Library',
            'marketplace' => 'Marketplace',
            'webhooks' => 'Webhooks',
            'models' => 'Models',
            'model-health' => 'Model Health',
            'custom-endpoints' => 'Custom Endpoints',
            'local-models' => 'Local Models',
            'air-gap' => 'Air-Gap',
            'billing' => 'Billing',
            'settings' => 'Settings',
            'auth' => 'Authentication',
            'api-tokens' => 'API Tokens',
            'diagnostics' => 'Diagnostics',
            'guardrails' => 'Guardrails',
            'guardrail-profiles' => 'Guardrails',
            'organizations' => 'Organizations',
            'performance' => 'Performance',
            'reports' => 'Reports',
            'import' => 'Import',
            'security-scan' => 'Security',
            'notifications' => 'Notifications',
            'analytics' => 'Analytics',
        ];

        return $tagMap[$first] ?? ucfirst($first);
    }

    protected function getTags(): array
    {
        return array_map(fn ($name) => ['name' => $name], [
            'Projects', 'Skills', 'Agents', 'Tags', 'Search', 'Library',
            'Marketplace', 'Webhooks', 'Models', 'Model Health', 'Custom Endpoints',
            'Local Models', 'Air-Gap', 'Billing', 'Settings', 'Authentication',
            'API Tokens', 'Diagnostics', 'Guardrails', 'Performance', 'Reports',
            'Import', 'Security', 'Notifications', 'Analytics',
        ]);
    }

    protected function buildOperationId($route, string $method): string
    {
        $action = $route->getActionName();
        if (str_contains($action, '@')) {
            $parts = explode('@', $action);
            $controller = class_basename($parts[0]);
            $controllerName = str_replace('Controller', '', $controller);
            $methodName = $parts[1];

            return lcfirst($controllerName) . ucfirst($methodName);
        }

        return $method . '_' . str_replace(['/', '{', '}', '-'], ['_', '', '', '_'], $route->uri());
    }

    protected function buildSummary($route, string $method): string
    {
        $action = $route->getActionName();
        if (str_contains($action, '@')) {
            $parts = explode('@', $action);
            $methodName = $parts[1];

            // Convert camelCase to words
            return ucfirst(trim(preg_replace('/([A-Z])/', ' $1', $methodName)));
        }

        return ucfirst($method) . ' ' . $route->uri();
    }
}
