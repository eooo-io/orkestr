<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Artifact extends Model
{
    protected $fillable = [
        'uuid',
        'project_id',
        'agent_id',
        'execution_run_id',
        'type',
        'title',
        'description',
        'content',
        'metadata',
        'format',
        'file_path',
        'file_size',
        'mime_type',
        'status',
        'version_number',
        'parent_artifact_id',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'file_size' => 'integer',
            'version_number' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Artifact $artifact) {
            if (empty($artifact->uuid)) {
                $artifact->uuid = (string) Str::uuid();
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function executionRun(): BelongsTo
    {
        return $this->belongsTo(ExecutionRun::class);
    }

    public function parentArtifact(): BelongsTo
    {
        return $this->belongsTo(Artifact::class, 'parent_artifact_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(Artifact::class, 'parent_artifact_id')->orderByDesc('version_number');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePendingReview($query)
    {
        return $query->where('status', 'pending_review');
    }

    /**
     * Get the root artifact in the version chain.
     */
    public function rootArtifact(): self
    {
        $current = $this;
        while ($current->parent_artifact_id) {
            $current = $current->parentArtifact;
        }

        return $current;
    }
}
