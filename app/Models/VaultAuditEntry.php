<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VaultAuditEntry extends Model
{
    protected $table = 'vault_audit_log';

    public $timestamps = false;

    protected $fillable = [
        'secret_id',
        'action',
        'actor_type',
        'actor_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (VaultAuditEntry $entry) {
            if (empty($entry->created_at)) {
                $entry->created_at = now();
            }
        });
    }

    // --- Relationships ---

    public function secret(): BelongsTo
    {
        return $this->belongsTo(VaultSecret::class, 'secret_id');
    }
}
