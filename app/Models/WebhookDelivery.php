<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'webhook_id',
        'event',
        'payload',
        'response_status',
        'response_body',
        'duration_ms',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response_status' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
