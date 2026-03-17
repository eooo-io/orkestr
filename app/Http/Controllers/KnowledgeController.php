<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentKnowledge;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeController extends Controller
{
    /**
     * Built-in namespace schemas — suggested structure for well-known namespaces.
     * Soft validation: warn but allow non-conforming values.
     */
    public const BUILTIN_NAMESPACES = [
        'facts' => [
            'description' => 'Factual knowledge about the world, domain, or project',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'statement' => ['type' => 'string'],
                    'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                    'source' => ['type' => 'string'],
                ],
                'required' => ['statement'],
            ],
        ],
        'preferences' => [
            'description' => 'User or agent preferences and settings',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'preference' => ['type' => 'string'],
                    'value' => [],
                    'reason' => ['type' => 'string'],
                ],
                'required' => ['preference', 'value'],
            ],
        ],
        'patterns' => [
            'description' => 'Recognized patterns, templates, or recurring structures',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'pattern' => ['type' => 'string'],
                    'frequency' => ['type' => 'integer'],
                    'context' => ['type' => 'string'],
                    'examples' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['pattern'],
            ],
        ],
        'contacts' => [
            'description' => 'People, organizations, or entities relevant to the agent',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'role' => ['type' => 'string'],
                    'email' => ['type' => 'string'],
                    'notes' => ['type' => 'string'],
                ],
                'required' => ['name'],
            ],
        ],
        'history' => [
            'description' => 'Historical events, decisions, or actions taken',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'event' => ['type' => 'string'],
                    'timestamp' => ['type' => 'string'],
                    'outcome' => ['type' => 'string'],
                    'context' => ['type' => 'string'],
                ],
                'required' => ['event'],
            ],
        ],
    ];

    /**
     * GET /api/projects/{project}/agents/{agent}/knowledge?namespace=
     */
    public function index(Request $request, Project $project, Agent $agent): JsonResponse
    {
        $query = AgentKnowledge::forAgent($agent->id, $project->id);

        if ($request->has('namespace')) {
            $query->inNamespace($request->query('namespace'));
        }

        $entries = $query->orderBy('updated_at', 'desc')->get();

        return response()->json(['data' => $entries]);
    }

    /**
     * POST /api/projects/{project}/agents/{agent}/knowledge
     */
    public function store(Request $request, Project $project, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'namespace' => 'required|string|max:100',
            'key' => 'required|string|max:255',
            'value' => 'required',
        ]);

        // Soft validation for built-in namespaces
        $warnings = $this->validateNamespace($validated['namespace'], $validated['value']);

        // Upsert: update if exists, create otherwise
        $entry = AgentKnowledge::forAgent($agent->id, $project->id)
            ->where('namespace', $validated['namespace'])
            ->where('key', $validated['key'])
            ->first();

        if ($entry) {
            $entry->update(['value' => $validated['value']]);
        } else {
            $entry = AgentKnowledge::create([
                'agent_id' => $agent->id,
                'project_id' => $project->id,
                'namespace' => $validated['namespace'],
                'key' => $validated['key'],
                'value' => $validated['value'],
            ]);
        }

        $response = ['data' => $entry];

        if (! empty($warnings)) {
            $response['warnings'] = $warnings;
        }

        return response()->json($response, 201);
    }

    /**
     * GET /api/projects/{project}/agents/{agent}/knowledge/search?q=
     */
    public function search(Request $request, Project $project, Agent $agent): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:500',
        ]);

        $query = $request->query('q');

        // Search across key and JSON value content
        $entries = AgentKnowledge::forAgent($agent->id, $project->id)
            ->where(function ($q) use ($query) {
                $q->where('key', 'like', "%{$query}%")
                    ->orWhere('namespace', 'like', "%{$query}%")
                    ->orWhere('value', 'like', "%{$query}%");
            })
            ->orderBy('updated_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json(['data' => $entries]);
    }

    /**
     * DELETE /api/agent-knowledge/{agentKnowledge}
     */
    public function destroy(int $id): JsonResponse
    {
        $entry = AgentKnowledge::findOrFail($id);
        $entry->delete();

        return response()->json(null, 204);
    }

    /**
     * Soft-validate a value against a built-in namespace schema.
     * Returns warning messages (never blocks the request).
     */
    private function validateNamespace(string $namespace, mixed $value): array
    {
        if (! isset(self::BUILTIN_NAMESPACES[$namespace])) {
            return [];
        }

        $schema = self::BUILTIN_NAMESPACES[$namespace]['schema'];
        $warnings = [];

        if (is_array($value) && isset($schema['required'])) {
            foreach ($schema['required'] as $required) {
                if (! array_key_exists($required, $value)) {
                    $warnings[] = "Built-in namespace '{$namespace}' expects field '{$required}' in value.";
                }
            }
        }

        return $warnings;
    }
}
