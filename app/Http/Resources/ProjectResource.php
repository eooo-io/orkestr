<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'path' => $this->path,
            'providers' => $this->whenLoaded('providers', fn () => $this->providers->pluck('provider_slug')->values()),
            'skills_count' => $this->whenCounted('skills'),
            'git_auto_commit' => $this->git_auto_commit,
            'synced_at' => $this->synced_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
