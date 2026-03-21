<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Services\ManifestService;
use App\Services\SkillFileParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SkillAssetController extends Controller
{
    public function __construct(
        protected ManifestService $manifestService,
        protected SkillFileParser $parser,
    ) {}

    /**
     * List all assets in a skill's folder.
     */
    public function index(Skill $skill): JsonResponse
    {
        $project = $skill->project;
        $folderPath = $this->manifestService->getSkillFolderPath($project->resolved_path, $skill->slug);

        if (! $folderPath) {
            return response()->json(['data' => [], 'is_folder' => false]);
        }

        $assets = $this->parser->inventoryAssets($folderPath);

        return response()->json(['data' => $assets, 'is_folder' => true]);
    }

    /**
     * Upload asset file(s) to a skill folder.
     */
    public function store(Request $request, Skill $skill): JsonResponse
    {
        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'required|file|max:10240', // 10MB per file
            'directory' => 'required|string|in:assets,scripts,data',
        ]);

        $project = $skill->project;

        // Ensure the skill has a folder structure
        $folderPath = $this->manifestService->ensureSkillFolder($project->resolved_path, $skill->slug);
        $targetDir = $folderPath . '/' . $request->input('directory');

        File::ensureDirectoryExists($targetDir);

        $uploaded = [];
        foreach ($request->file('files') as $file) {
            $filename = $file->getClientOriginalName();
            // Sanitize filename — prevent path traversal
            $filename = basename($filename);
            $file->move($targetDir, $filename);

            $uploaded[] = [
                'path' => $request->input('directory') . '/' . $filename,
                'name' => $filename,
                'directory' => $request->input('directory'),
                'size' => File::size($targetDir . '/' . $filename),
                'type' => pathinfo($filename, PATHINFO_EXTENSION),
            ];
        }

        return response()->json(['data' => $uploaded, 'message' => count($uploaded) . ' file(s) uploaded'], 201);
    }

    /**
     * Download/preview a specific asset from a skill folder.
     */
    public function show(Skill $skill, string $path): BinaryFileResponse|JsonResponse
    {
        $project = $skill->project;
        $folderPath = $this->manifestService->getSkillFolderPath($project->resolved_path, $skill->slug);

        if (! $folderPath) {
            return response()->json(['error' => 'Skill is not in folder format'], 404);
        }

        $fullPath = $folderPath . '/' . $path;

        // Prevent path traversal
        $realFolder = realpath($folderPath);
        $realFile = realpath($fullPath);

        if (! $realFile || ! str_starts_with($realFile, $realFolder)) {
            return response()->json(['error' => 'Invalid path'], 403);
        }

        if (! File::exists($fullPath)) {
            return response()->json(['error' => 'Asset not found'], 404);
        }

        return response()->file($fullPath);
    }

    /**
     * Delete an asset from a skill folder.
     */
    public function destroy(Skill $skill, string $path): JsonResponse
    {
        $project = $skill->project;
        $folderPath = $this->manifestService->getSkillFolderPath($project->resolved_path, $skill->slug);

        if (! $folderPath) {
            return response()->json(['error' => 'Skill is not in folder format'], 404);
        }

        $fullPath = $folderPath . '/' . $path;

        // Prevent path traversal
        $realFolder = realpath($folderPath);
        $realFile = realpath($fullPath);

        if (! $realFile || ! str_starts_with($realFile, $realFolder)) {
            return response()->json(['error' => 'Invalid path'], 403);
        }

        if (! File::exists($fullPath)) {
            return response()->json(['error' => 'Asset not found'], 404);
        }

        File::delete($fullPath);

        return response()->json(['message' => 'Asset deleted']);
    }
}
