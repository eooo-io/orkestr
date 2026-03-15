<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupRestoreCommand extends Command
{
    protected $signature = 'orkestr:restore {path : Path to the backup ZIP file}';

    protected $description = 'Restore the instance from a backup ZIP file';

    public function handle(BackupService $backupService): int
    {
        $path = $this->argument('path');

        if (! file_exists($path)) {
            $this->error("Backup file not found: {$path}");

            return self::FAILURE;
        }

        $this->warn('This will overwrite all existing data with the backup contents.');

        if (! $this->confirm('Are you sure you want to proceed?')) {
            $this->info('Restore cancelled.');

            return self::SUCCESS;
        }

        $this->info('Restoring from backup...');

        $start = microtime(true);
        $result = $backupService->restoreFromZip($path);
        $elapsed = round(microtime(true) - $start, 2);

        $this->newLine();
        $this->info('Restore completed successfully!');
        $this->line("  Backup version: {$result['version']}");
        $this->line("  Backup created: {$result['created_at']}");
        $this->line("  Tables restored: " . count($result['tables_restored']));
        $this->line("  Time: {$elapsed}s");

        $this->newLine();
        $this->table(
            ['Table'],
            array_map(fn ($t) => [$t], $result['tables_restored'])
        );

        return self::SUCCESS;
    }
}
