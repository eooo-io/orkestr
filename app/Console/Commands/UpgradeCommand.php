<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class UpgradeCommand extends Command
{
    protected $signature = 'orkestr:upgrade {--skip-backup : Skip creating a backup before upgrading}';

    protected $description = 'Run upgrade steps: backup, migrate, clear caches, update version';

    public function handle(BackupService $backupService): int
    {
        $start = microtime(true);
        $version = config('app.version', '1.0.0');

        $this->info("Starting upgrade (current version: {$version})...");
        $this->newLine();

        // Step 1: Backup
        if (! $this->option('skip-backup')) {
            $this->info('[1/7] Creating backup...');
            $zipPath = $backupService->createBackup();
            $this->line("  Backup saved to: {$zipPath}");
        } else {
            $this->info('[1/7] Skipping backup (--skip-backup)');
        }

        // Step 2: Migrate
        $this->info('[2/7] Running database migrations...');
        Artisan::call('migrate', ['--force' => true]);
        $this->line('  ' . trim(Artisan::output()));

        // Step 3: Clear config cache
        $this->info('[3/7] Clearing config cache...');
        Artisan::call('config:clear');
        $this->line('  Done.');

        // Step 4: Clear application cache
        $this->info('[4/7] Clearing application cache...');
        Artisan::call('cache:clear');
        $this->line('  Done.');

        // Step 5: Clear view cache
        $this->info('[5/7] Clearing view cache...');
        Artisan::call('view:clear');
        $this->line('  Done.');

        // Step 6: Clear route cache
        $this->info('[6/7] Clearing route cache...');
        Artisan::call('route:clear');
        $this->line('  Done.');

        // Step 7: Update app settings
        $this->info('[7/7] Updating app settings...');
        AppSetting::set('last_upgrade_at', now()->toIso8601String());
        AppSetting::set('app_version', $version);
        $this->line('  Done.');

        $elapsed = round(microtime(true) - $start, 2);

        $this->newLine();
        $this->info("Upgrade completed successfully in {$elapsed}s.");

        return self::SUCCESS;
    }
}
