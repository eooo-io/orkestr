<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FederatedIdentity extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'peer_id',
        'remote_user_id',
        'remote_email',
        'remote_role',
        'verified_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FederatedIdentity $identity) {
            if (empty($identity->created_at)) {
                $identity->created_at = now();
            }
        });
    }

    // --- Relationships ---

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function peer(): BelongsTo
    {
        return $this->belongsTo(FederationPeer::class, 'peer_id');
    }
}
