<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupCreateCommand extends Command
{
    protected $signature = 'orkestr:backup';

    protected $description = 'Create a full instance backup as a ZIP file';

    public function handle(BackupService $backupService): int
    {
        $this->info('Creating backup...');

        $start = microtime(true);
        $zipPath = $backupService->createBackup();
        $elapsed = round(microtime(true) - $start, 2);

        $size = filesize($zipPath);
        $sizeHuman = $this->humanFileSize($size);

        $this->newLine();
        $this->info('Backup created successfully!');
        $this->line("  Path: {$zipPath}");
        $this->line("  Size: {$sizeHuman}");
        $this->line("  Time: {$elapsed}s");

        return self::SUCCESS;
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
