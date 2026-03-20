<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ControlPlaneMessage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'role',
        'content',
        'tool_calls',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'tool_calls' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ControlPlaneMessage $message) {
            if (empty($message->created_at)) {
                $message->created_at = now();
            }
        });
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ControlPlaneSession::class, 'session_id');
    }
}
