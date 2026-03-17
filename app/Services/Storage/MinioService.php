<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\Storage;

class MinioService
{
    protected string $disk = 'minio';

    /**
     * Upload content to MinIO.
     */
    public function upload(string $path, string $content, ?string $contentType = null): bool
    {
        $options = [];
        if ($contentType) {
            $options['ContentType'] = $contentType;
        }

        return Storage::disk($this->disk)->put($path, $content, $options);
    }

    /**
     * Download content from MinIO.
     */
    public function download(string $path): string
    {
        return Storage::disk($this->disk)->get($path);
    }

    /**
     * List files at a given prefix.
     *
     * @return array<int, string>
     */
    public function list(?string $prefix = null): array
    {
        return Storage::disk($this->disk)->files($prefix ?? '');
    }

    /**
     * Delete a file from MinIO.
     */
    public function delete(string $path): bool
    {
        return Storage::disk($this->disk)->delete($path);
    }

    /**
     * Check if a file exists in MinIO.
     */
    public function exists(string $path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }

    /**
     * Get the public URL for a file.
     */
    public function getUrl(string $path): string
    {
        return Storage::disk($this->disk)->url($path);
    }

    /**
     * Get the file size in bytes.
     */
    public function size(string $path): int
    {
        return Storage::disk($this->disk)->size($path);
    }

    /**
     * Build a scoped path for agent/project artifacts.
     */
    public function scopedPath(string $agentSlug, int $projectId, string $path): string
    {
        return "projects/{$projectId}/agents/{$agentSlug}/{$path}";
    }

    /**
     * Upload with automatic agent/project scoping.
     */
    public function scopedUpload(string $agentSlug, int $projectId, string $path, string $content, ?string $contentType = null): bool
    {
        return $this->upload($this->scopedPath($agentSlug, $projectId, $path), $content, $contentType);
    }

    /**
     * Download with automatic agent/project scoping.
     */
    public function scopedDownload(string $agentSlug, int $projectId, string $path): string
    {
        return $this->download($this->scopedPath($agentSlug, $projectId, $path));
    }

    /**
     * List files with automatic agent/project scoping.
     *
     * @return array<int, string>
     */
    public function scopedList(string $agentSlug, int $projectId, ?string $path = null): array
    {
        $prefix = "projects/{$projectId}/agents/{$agentSlug}";
        if ($path) {
            $prefix .= "/{$path}";
        }

        return $this->list($prefix);
    }

    /**
     * Delete with automatic agent/project scoping.
     */
    public function scopedDelete(string $agentSlug, int $projectId, string $path): bool
    {
        return $this->delete($this->scopedPath($agentSlug, $projectId, $path));
    }
}
