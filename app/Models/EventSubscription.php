<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSubscription extends Model
{
    protected $fillable = [
        'topic_id',
        'subscriber_type',
        'subscriber_id',
        'filter_expression',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'filter_expression' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(EventTopic::class, 'topic_id');
    }

    /**
     * Redis consumer group name for this subscription.
     */
    public function consumerGroupName(): string
    {
        return "sub:{$this->subscriber_type}:{$this->subscriber_id}:{$this->id}";
    }
}
