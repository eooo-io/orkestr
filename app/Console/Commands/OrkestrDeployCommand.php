<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class OrkestrDeployCommand extends Command
{
    protected $signature = 'orkestr:deploy
                            {--check : Only check deployment readiness without deploying}';

    protected $description = 'Run deployment checks and apply migrations';

    public function handle(): int
    {
        $this->info('Orkestr Deploy');
        $this->newLine();

        // Check PHP version
        $phpVersion = PHP_VERSION;
        $phpOk = version_compare($phpVersion, '8.4.0', '>=');
        $this->line(($phpOk ? '[OK]' : '[FAIL]') . " PHP version: {$phpVersion}");

        // Check required extensions
        $requiredExtensions = ['pdo', 'mbstring', 'openssl', 'curl', 'json', 'fileinfo'];
        foreach ($requiredExtensions as $ext) {
            $loaded = extension_loaded($ext);
            $this->line(($loaded ? '[OK]' : '[FAIL]') . " Extension: {$ext}");
        }

        // Check database connection
        try {
            \DB::connection()->getPdo();
            $this->line('[OK] Database connection');
        } catch (\Throwable $e) {
            $this->line('[FAIL] Database connection: ' . $e->getMessage());
        }

        // Check storage writable
        $storageWritable = is_writable(storage_path());
        $this->line(($storageWritable ? '[OK]' : '[FAIL]') . ' Storage directory writable');

        // Check .env exists
        $envExists = file_exists(base_path('.env'));
        $this->line(($envExists ? '[OK]' : '[FAIL]') . ' .env file exists');

        if ($this->option('check')) {
            $this->newLine();
            $this->info('Deployment check complete (dry run).');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Running migrations...');
        $this->call('migrate', ['--force' => true]);

        $this->info('Clearing caches...');
        $this->call('config:cache');
        $this->call('route:cache');
        $this->call('view:cache');

        $this->newLine();
        $this->info('Deployment complete.');

        return self::SUCCESS;
    }
}
