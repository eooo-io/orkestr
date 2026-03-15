<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupListCommand extends Command
{
    protected $signature = 'orkestr:backup:list';

    protected $description = 'List available instance backups';

    public function handle(BackupService $backupService): int
    {
        $backups = $backupService->listBackups();

        if (empty($backups)) {
            $this->info('No backups found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Filename', 'Size', 'Created At'],
            array_map(fn ($b) => [
                $b['filename'],
                $b['size_human'],
                $b['created_at'],
            ], $backups)
        );

        return self::SUCCESS;
    }
}
