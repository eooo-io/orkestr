<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRepository extends Model
{
    protected $fillable = [
        'project_id',
        'provider',
        'owner',
        'name',
        'full_name',
        'default_branch',
        'url',
        'clone_url',
        'access_token',
        'webhook_secret',
        'auto_scan_on_push',
        'auto_sync_on_push',
        'last_synced_at',
        'last_commit_sha',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'auto_scan_on_push' => 'boolean',
            'auto_sync_on_push' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isGitHub(): bool
    {
        return $this->provider === 'github';
    }

    public function isGitLab(): bool
    {
        return $this->provider === 'gitlab';
    }

    public function hasAccessToken(): bool
    {
        return ! empty($this->access_token);
    }

    public function apiBaseUrl(): string
    {
        return match ($this->provider) {
            'github' => "https://api.github.com/repos/{$this->full_name}",
            'gitlab' => "https://gitlab.com/api/v4/projects/" . urlencode($this->full_name),
            default => throw new \InvalidArgumentException("Unsupported provider: {$this->provider}"),
        };
    }
}
