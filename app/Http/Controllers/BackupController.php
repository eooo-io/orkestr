<?php

namespace App\Http\Controllers;

use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class BackupController extends Controller
{
    public function __construct(
        private BackupService $backupService,
    ) {}

    /**
     * List available backups.
     */
    public function index(): JsonResponse
    {
        return response()->json($this->backupService->listBackups());
    }

    /**
     * Create a new backup.
     */
    public function store(): JsonResponse
    {
        $zipPath = $this->backupService->createBackup();
        $filename = basename($zipPath);

        return response()->json([
            'message' => 'Backup created successfully.',
            'filename' => $filename,
            'download_url' => url("/api/backups/{$filename}/download"),
            'size' => filesize($zipPath),
        ], 201);
    }

    /**
     * Restore from an uploaded backup ZIP.
     */
    public function restore(Request $request): JsonResponse
    {
        $request->validate([
            'backup' => 'required|file|mimes:zip|max:512000',
        ]);

        $file = $request->file('backup');
        $tempPath = $file->storeAs('backups', 'restore-upload-' . $file->getClientOriginalName(), 'local');
        $fullPath = storage_path("app/{$tempPath}");

        try {
            $result = $this->backupService->restoreFromZip($fullPath);

            return response()->json($result);
        } finally {
            File::delete($fullPath);
        }
    }

    /**
     * Download a backup file.
     */
    public function download(string $filename): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        // Sanitize filename to prevent directory traversal
        $filename = basename($filename);
        $path = storage_path("backups/{$filename}");

        if (! File::exists($path) || ! str_ends_with($filename, '.zip')) {
            return response()->json(['message' => 'Backup not found.'], 404);
        }

        return response()->download($path);
    }
}
