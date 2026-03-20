<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventLog extends Model
{
    protected $table = 'event_log';

    protected $fillable = [
        'topic_id',
        'publisher_type',
        'publisher_id',
        'event_type',
        'payload',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(EventTopic::class, 'topic_id');
    }
}
