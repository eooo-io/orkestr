<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\BundleExportService;
use App\Services\BundleImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BundleController extends Controller
{
    public function __construct(
        protected BundleExportService $exportService,
        protected BundleImportService $importService,
    ) {}

    /**
     * Export skills and/or agents as a ZIP bundle.
     */
    public function export(Request $request, Project $project): JsonResponse|BinaryFileResponse
    {
        $validated = $request->validate([
            'skill_ids' => 'nullable|array',
            'skill_ids.*' => 'integer|exists:skills,id',
            'agent_ids' => 'nullable|array',
            'agent_ids.*' => 'integer|exists:agents,id',
            'content_format' => 'required|in:json,yaml,markdown,toon',
        ]);

        $skillIds = $validated['skill_ids'] ?? [];
        $agentIds = $validated['agent_ids'] ?? [];
        $contentFormat = $validated['content_format'];

        if (empty($skillIds) && empty($agentIds)) {
            return response()->json(['message' => 'No skills or agents selected for export.'], 422);
        }

        $zipPath = $this->exportService->exportZip($project, $skillIds, $agentIds, $contentFormat);
        $filename = \Illuminate\Support\Str::slug($project->name) . '-bundle.zip';

        return response()
            ->download($zipPath, $filename, [
                'Content-Type' => 'application/zip',
            ])
            ->deleteFileAfterSend(true);
    }

    /**
     * Import a bundle into a project.
     */
    public function import(Request $request, Project $project): JsonResponse
    {
        $isPreview = $request->boolean('preview');

        // Determine import source: file upload or JSON body
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());

            if ($extension === 'zip') {
                $tempPath = $file->getRealPath();

                if ($isPreview) {
                    $preview = $this->importService->previewZip($tempPath);

                    return response()->json($preview);
                }

                $conflictMode = $request->input('conflict_mode', 'skip');
                $extracted = $this->importService->extractZip($tempPath);

                $result = $this->importService->import(
                    $project,
                    $extracted['skills'],
                    $extracted['agents'],
                    $conflictMode,
                );

                return response()->json($result);
            }

            if ($extension === 'json') {
                $content = file_get_contents($file->getRealPath());
                $data = json_decode($content, true);

                if (! is_array($data)) {
                    return response()->json(['message' => 'Invalid JSON file.'], 422);
                }

                if ($isPreview) {
                    $preview = $this->importService->previewJson($data);

                    return response()->json($preview);
                }

                $conflictMode = $request->input('conflict_mode', 'skip');

                $result = $this->importService->import(
                    $project,
                    $data['skills'] ?? [],
                    $data['agents'] ?? [],
                    $conflictMode,
                );

                return response()->json($result);
            }

            return response()->json(['message' => 'Unsupported file type. Use .zip or .json.'], 422);
        }

        // JSON body
        $data = $request->validate([
            'bundle' => 'required|array',
            'bundle.skills' => 'nullable|array',
            'bundle.agents' => 'nullable|array',
            'bundle.metadata' => 'nullable|array',
            'conflict_mode' => 'nullable|in:skip,overwrite,rename',
        ]);

        $bundle = $data['bundle'];

        if ($isPreview) {
            $preview = $this->importService->previewJson($bundle);

            return response()->json($preview);
        }

        $conflictMode = $data['conflict_mode'] ?? 'skip';

        $result = $this->importService->import(
            $project,
            $bundle['skills'] ?? [],
            $bundle['agents'] ?? [],
            $conflictMode,
        );

        return response()->json($result);
    }
}
