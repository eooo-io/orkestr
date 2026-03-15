<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BackupService
{
    /**
     * Create a full instance backup as a ZIP file.
     * Returns the path to the created ZIP.
     */
    public function createBackup(): string
    {
        $timestamp = now()->format('Y-m-d_His');
        $backupDir = storage_path("backups/{$timestamp}");
        $zipPath = storage_path("backups/orkestr-backup-{$timestamp}.zip");

        // Create temp dir
        File::makeDirectory($backupDir, 0755, true);

        try {
            // 1. Export database tables as JSON
            $this->exportDatabase($backupDir);

            // 2. Copy app settings
            $this->exportSettings($backupDir);

            // 3. Create manifest
            File::put("{$backupDir}/manifest.json", json_encode([
                'version' => config('app.version', '1.0.0'),
                'created_at' => now()->toIso8601String(),
                'tables' => $this->getExportableTables(),
            ], JSON_PRETTY_PRINT));

            // 4. ZIP it
            $this->createZip($backupDir, $zipPath);

            return $zipPath;
        } finally {
            // Clean up temp dir
            File::deleteDirectory($backupDir);
        }
    }

    /**
     * Restore from a backup ZIP file.
     */
    public function restoreFromZip(string $zipPath): array
    {
        $extractDir = storage_path('backups/restore-' . Str::random(8));
        File::makeDirectory($extractDir, 0755, true);

        try {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new \RuntimeException('Could not open backup ZIP.');
            }
            $zip->extractTo($extractDir);
            $zip->close();

            // Read manifest
            $manifest = json_decode(File::get("{$extractDir}/manifest.json"), true);
            if (! $manifest) {
                throw new \RuntimeException('Invalid backup: missing or corrupt manifest.');
            }

            // Restore tables
            $restored = $this->importDatabase($extractDir, $manifest);

            return [
                'success' => true,
                'version' => $manifest['version'],
                'tables_restored' => $restored,
                'created_at' => $manifest['created_at'],
            ];
        } finally {
            File::deleteDirectory($extractDir);
        }
    }

    /**
     * List available backups.
     */
    public function listBackups(): array
    {
        $dir = storage_path('backups');
        if (! File::isDirectory($dir)) {
            return [];
        }

        return collect(File::files($dir))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.zip'))
            ->map(fn ($f) => [
                'filename' => $f->getFilename(),
                'path' => $f->getPathname(),
                'size' => $f->getSize(),
                'size_human' => $this->humanFileSize($f->getSize()),
                'created_at' => date('Y-m-d H:i:s', $f->getMTime()),
            ])
            ->sortByDesc('created_at')
            ->values()
            ->toArray();
    }

    private function getExportableTables(): array
    {
        return [
            'organizations', 'users', 'organization_user',
            'projects', 'skills', 'skill_versions', 'tags', 'skill_tag',
            'agents', 'project_agent', 'agent_skill',
            'app_settings', 'content_policies', 'sso_providers',
            'webhooks', 'mcp_servers',
        ];
    }

    private function exportDatabase(string $dir): void
    {
        $dbDir = "{$dir}/database";
        File::makeDirectory($dbDir);

        foreach ($this->getExportableTables() as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $data = DB::table($table)->get()->toArray();
            File::put("{$dbDir}/{$table}.json", json_encode($data, JSON_PRETTY_PRINT));
        }
    }

    private function exportSettings(string $dir): void
    {
        // Export .env keys that are safe (non-secret)
        $settings = AppSetting::all()->mapWithKeys(fn ($s) => [$s->key => $s->value]);
        File::put("{$dir}/app_settings.json", json_encode($settings, JSON_PRETTY_PRINT));
    }

    private function importDatabase(string $dir, array $manifest): array
    {
        $restored = [];
        $dbDir = "{$dir}/database";

        if (! File::isDirectory($dbDir)) {
            return $restored;
        }

        foreach ($manifest['tables'] ?? [] as $table) {
            $file = "{$dbDir}/{$table}.json";
            if (! File::exists($file)) {
                continue;
            }

            $data = json_decode(File::get($file), true);
            if (empty($data)) {
                continue;
            }

            // Truncate and re-insert (within transaction)
            DB::transaction(function () use ($table, $data) {
                DB::table($table)->truncate();
                foreach (array_chunk($data, 500) as $chunk) {
                    // Convert stdClass to array if needed
                    $rows = array_map(fn ($row) => (array) $row, $chunk);
                    DB::table($table)->insert($rows);
                }
            });

            $restored[] = $table;
        }

        return $restored;
    }

    private function createZip(string $sourceDir, string $zipPath): void
    {
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                continue;
            }
            $relativePath = substr($file->getPathname(), strlen($sourceDir) + 1);
            $zip->addFile($file->getPathname(), $relativePath);
        }

        $zip->close();
    }

    private function humanFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
