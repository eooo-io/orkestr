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
            'default_model' => $this->default_model,
            'monthly_budget_usd' => $this->monthly_budget_usd ? (float) $this->monthly_budget_usd : null,
            'environment' => $this->environment,
            'icon' => $this->icon,
            'color' => $this->color,
            'providers' => $this->whenLoaded('providers', fn () => $this->providers->pluck('provider_slug')->values()),
            'skills_count' => $this->whenCounted('skills'),
            'git_auto_commit' => $this->git_auto_commit,
            'repositories' => $this->whenLoaded('repositories', fn () => $this->repositories->map(fn ($repo) => [
                'id' => $repo->id,
                'provider' => $repo->provider,
                'full_name' => $repo->full_name,
                'url' => $repo->url,
                'default_branch' => $repo->default_branch,
                'auto_scan_on_push' => $repo->auto_scan_on_push,
                'auto_sync_on_push' => $repo->auto_sync_on_push,
                'last_synced_at' => $repo->last_synced_at?->toIso8601String(),
            ])),
            'synced_at' => $this->synced_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
