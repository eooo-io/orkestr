<?php

namespace App\Http\Resources;

use App\Services\AgentComposeService;
use App\Services\SkillCompositionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SkillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $compositionService = app(SkillCompositionService::class);
        $resolvedBody = $compositionService->resolve($this->resource);

        // Only run filesystem checks (folder detection + asset inventory) when the
        // project relation is eager-loaded (single resource / show endpoint). This
        // avoids N+1 filesystem reads on collection / index endpoints.
        $assets = [];
        $isFolder = false;
        if ($this->relationLoaded('project')) {
            $project = $this->project;
            if ($project && $project->resolved_path) {
                $manifestService = app(\App\Services\ManifestService::class);
                $folderPath = $manifestService->getSkillFolderPath($project->resolved_path, $this->slug);
                if ($folderPath) {
                    $isFolder = true;
                    $parser = app(\App\Services\SkillFileParser::class);
                    $assets = $parser->inventoryAssets($folderPath);
                }
            }
        }

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'project_id' => $this->project_id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'summary' => $this->summary,
            'model' => $this->model,
            'max_tokens' => $this->max_tokens,
            'tools' => $this->tools ?? [],
            'includes' => $this->includes ?? [],
            'conditions' => $this->conditions,
            'template_variables' => $this->template_variables,
            'body' => $this->body,
            'resolved_body' => $resolvedBody,
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')->values()),
            'project' => new ProjectResource($this->whenLoaded('project')),
            'token_estimate' => app(AgentComposeService::class)->estimateTokens($resolvedBody),
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => [
                'slug' => $this->category->slug,
                'name' => $this->category->name,
                'icon' => $this->category->icon,
                'color' => $this->category->color,
            ]),
            'skill_type' => $this->skill_type,
            'tuned_for_model' => $this->tuned_for_model,
            'last_validated_model' => $this->last_validated_model,
            'last_validated_at' => $this->last_validated_at?->toIso8601String(),
            'last_validated_eval_run_id' => $this->last_validated_eval_run_id,
            'is_folder' => $isFolder,
            'assets' => $assets,
            'asset_count' => count($assets),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
