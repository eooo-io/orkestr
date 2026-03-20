<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class KnowledgeGraphNode extends Model
{
    protected $fillable = [
        'uuid',
        'project_id',
        'pool_id',
        'entity_type',
        'entity_name',
        'properties',
        'embedding',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'embedding' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (KnowledgeGraphNode $node) {
            if (empty($node->uuid)) {
                $node->uuid = (string) Str::uuid();
            }
        });
    }

    // --- Relationships ---

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(SharedMemoryPool::class, 'pool_id');
    }

    public function outgoingEdges(): HasMany
    {
        return $this->hasMany(KnowledgeGraphEdge::class, 'source_node_id');
    }

    public function incomingEdges(): HasMany
    {
        return $this->hasMany(KnowledgeGraphEdge::class, 'target_node_id');
    }
}
