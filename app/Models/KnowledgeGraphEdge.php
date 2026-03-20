<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeGraphEdge extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'source_node_id',
        'target_node_id',
        'relationship',
        'properties',
        'weight',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'weight' => 'decimal:4',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (KnowledgeGraphEdge $edge) {
            if (empty($edge->created_at)) {
                $edge->created_at = now();
            }
        });
    }

    // --- Relationships ---

    public function sourceNode(): BelongsTo
    {
        return $this->belongsTo(KnowledgeGraphNode::class, 'source_node_id');
    }

    public function targetNode(): BelongsTo
    {
        return $this->belongsTo(KnowledgeGraphNode::class, 'target_node_id');
    }
}
