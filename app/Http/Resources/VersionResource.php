<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'skill_id' => $this->skill_id,
            'version_number' => $this->version_number,
            'frontmatter' => $this->frontmatter,
            'body' => $this->body,
            'note' => $this->note,
            'saved_at' => $this->saved_at->toIso8601String(),
        ];
    }
}
