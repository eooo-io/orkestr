<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'role' => $this->pivot->role,
            'accepted_at' => $this->pivot->accepted_at,
            'joined_at' => $this->pivot->created_at?->toIso8601String(),
        ];
    }
}
