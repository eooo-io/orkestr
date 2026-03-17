<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Memory\DocumentIndexer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /**
     * Resolve which disk to use — minio if configured, otherwise local.
     */
    private function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        try {
            $minioDisk = Storage::disk('minio');
            // Test connectivity by checking adapter (fast, no network call for local)
            if (config('filesystems.disks.minio.endpoint')) {
                return $minioDisk;
            }
        } catch (\Throwable) {
            // MinIO not available (missing S3 driver or connection error), fall back to local
        }

        return Storage::disk('local');
    }

    /**
     * Build the scoped path prefix for a project's documents.
     */
    private function projectPrefix(Project $project): string
    {
        return "documents/projects/{$project->id}";
    }

    /**
     * Validate and sanitize a relative path (prevent directory traversal).
     */
    private function sanitizePath(string $path): string
    {
        // Remove directory traversal sequences and leading slashes
        $clean = str_replace('..', '', $path);
        $clean = ltrim($clean, '/');

        return $clean;
    }

    /**
     * GET /api/projects/{project}/documents?prefix=
     */
    public function index(Request $request, Project $project): JsonResponse
    {
        $prefix = $this->projectPrefix($project);

        if ($request->has('prefix')) {
            $prefix .= '/' . $this->sanitizePath($request->query('prefix'));
        }

        $disk = $this->disk();
        $files = $disk->files($prefix);

        // Map to relative paths (strip the project prefix)
        $basePrefix = $this->projectPrefix($project) . '/';
        $documents = array_map(function (string $file) use ($basePrefix, $disk) {
            return [
                'path' => str_replace($basePrefix, '', $file),
                'size' => $disk->size($file),
                'last_modified' => $disk->lastModified($file),
            ];
        }, $files);

        return response()->json(['data' => array_values($documents)]);
    }

    /**
     * POST /api/projects/{project}/documents
     *
     * Accepts multipart form (file upload) or JSON body with path+content.
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $disk = $this->disk();

        if ($request->hasFile('file')) {
            $request->validate([
                'file' => 'required|file|max:51200', // 50MB max
                'path' => 'nullable|string|max:500',
            ]);

            $file = $request->file('file');
            $relativePath = $request->input('path', $file->getClientOriginalName());
            $relativePath = $this->sanitizePath($relativePath);
            $fullPath = $this->projectPrefix($project) . '/' . $relativePath;

            $disk->put($fullPath, $file->getContent());
        } else {
            $validated = $request->validate([
                'path' => 'required|string|max:500',
                'content' => 'required|string',
            ]);

            $relativePath = $this->sanitizePath($validated['path']);
            $fullPath = $this->projectPrefix($project) . '/' . $relativePath;

            $disk->put($fullPath, $validated['content']);
        }

        // Auto-index for RAG if content is text-based
        if (! $request->hasFile('file') && $request->has('agent_id')) {
            try {
                $indexer = app(DocumentIndexer::class);
                $indexer->index(
                    agentId: (int) $request->input('agent_id'),
                    projectId: $project->id,
                    path: $relativePath,
                    content: $request->input('content'),
                );
            } catch (\Exception $e) {
                // Non-fatal: indexing failure shouldn't block upload
                logger()->warning('Document indexing failed: ' . $e->getMessage());
            }
        }

        return response()->json([
            'data' => [
                'path' => $relativePath,
                'size' => $disk->size($fullPath),
            ],
        ], 201);
    }

    /**
     * GET /api/projects/{project}/documents/download?path=
     */
    public function download(Request $request, Project $project): \Symfony\Component\HttpFoundation\Response
    {
        $request->validate([
            'path' => 'required|string|max:500',
        ]);

        $relativePath = $this->sanitizePath($request->query('path'));
        $fullPath = $this->projectPrefix($project) . '/' . $relativePath;
        $disk = $this->disk();

        if (! $disk->exists($fullPath)) {
            return response()->json(['error' => 'Document not found'], 404);
        }

        $content = $disk->get($fullPath);
        $mimeType = $disk->mimeType($fullPath) ?: 'application/octet-stream';

        return response($content, 200)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', 'attachment; filename="' . basename($relativePath) . '"');
    }

    /**
     * DELETE /api/projects/{project}/documents?path=
     */
    public function destroy(Request $request, Project $project): JsonResponse
    {
        $request->validate([
            'path' => 'required|string|max:500',
        ]);

        $relativePath = $this->sanitizePath($request->query('path'));
        $fullPath = $this->projectPrefix($project) . '/' . $relativePath;
        $disk = $this->disk();

        if (! $disk->exists($fullPath)) {
            return response()->json(['error' => 'Document not found'], 404);
        }

        $disk->delete($fullPath);

        // Remove RAG index entries if agent_id provided
        if ($request->has('agent_id')) {
            try {
                $indexer = app(DocumentIndexer::class);
                $indexer->removeIndex(
                    agentId: (int) $request->input('agent_id'),
                    projectId: $project->id,
                    path: $relativePath,
                );
            } catch (\Exception $e) {
                logger()->warning('Document index removal failed: ' . $e->getMessage());
            }
        }

        return response()->json(null, 204);
    }
}
