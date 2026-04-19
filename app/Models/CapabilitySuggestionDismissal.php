<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapabilitySuggestionDismissal extends Model
{
    protected $fillable = [
        'user_id',
        'agent_id',
        'suggestion_key',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }
}
