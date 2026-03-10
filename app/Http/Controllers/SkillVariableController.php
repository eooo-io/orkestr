<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Skill;
use App\Models\SkillVariable;
use App\Services\TemplateResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillVariableController extends Controller
{
    public function __construct(
        protected TemplateResolver $templateResolver,
    ) {}

    /**
     * Get current variable values for a skill in a project.
     */
    public function index(Project $project, Skill $skill): JsonResponse
    {
        $variables = SkillVariable::where('project_id', $project->id)
            ->where('skill_id', $skill->id)
            ->get()
            ->map(fn (SkillVariable $v) => [
                'key' => $v->key,
                'value' => $v->value,
            ]);

        // Also extract variables found in the body so the UI knows what's available
        $bodyVariables = $this->templateResolver->extractVariables($skill->body ?? '');

        // Merge template_variables definitions with actual values
        $definitions = $skill->template_variables ?? [];
        $valueMap = $variables->keyBy('key');

        $merged = collect($definitions)->map(fn (array $def) => [
            'name' => $def['name'],
            'description' => $def['description'] ?? '',
            'default' => $def['default'] ?? null,
            'value' => $valueMap->has($def['name']) ? $valueMap[$def['name']]['value'] : null,
        ]);

        return response()->json([
            'data' => [
                'definitions' => $merged->values(),
                'values' => $variables->values(),
                'body_variables' => $bodyVariables,
            ],
        ]);
    }

    /**
     * Save/update variable values for a skill in a project.
     */
    public function update(Request $request, Project $project, Skill $skill): JsonResponse
    {
        $validated = $request->validate([
            'variables' => 'required|array',
            'variables.*' => 'nullable|string|max:10000',
        ]);

        foreach ($validated['variables'] as $key => $value) {
            SkillVariable::updateOrCreate(
                [
                    'project_id' => $project->id,
                    'skill_id' => $skill->id,
                    'key' => $key,
                ],
                ['value' => $value],
            );
        }

        $variables = SkillVariable::where('project_id', $project->id)
            ->where('skill_id', $skill->id)
            ->get()
            ->map(fn (SkillVariable $v) => [
                'key' => $v->key,
                'value' => $v->value,
            ]);

        return response()->json([
            'data' => $variables->values(),
            'message' => 'Variables saved',
        ]);
    }
}
