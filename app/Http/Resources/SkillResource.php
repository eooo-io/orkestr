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

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'project_id' => $this->project_id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
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
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
