<?php

namespace App\Console\Commands;

use App\Models\LicenseKey;
use Illuminate\Console\Command;

class LicenseStatusCommand extends Command
{
    protected $signature = 'orkestr:license:status';

    protected $description = 'Show current license information';

    public function handle(): int
    {
        $licenses = LicenseKey::active()->get();

        if ($licenses->isEmpty()) {
            $this->warn('No active licenses found.');
            $this->newLine();
            $this->line('Generate a license with: php artisan orkestr:license:generate {tier}');

            return self::SUCCESS;
        }

        $this->info("Active Licenses ({$licenses->count()})");
        $this->newLine();

        $rows = $licenses->map(fn (LicenseKey $license) => [
            $license->key,
            $license->tier,
            $license->status,
            $license->max_users === 0 ? 'Unlimited' : $license->max_users,
            $license->max_agents === 0 ? 'Unlimited' : $license->max_agents,
            $license->licensee_name ?? '-',
            $license->licensee_email ?? '-',
            $license->activated_at?->toDateString() ?? '-',
            $license->expires_at?->toDateString() ?? 'Never',
        ]);

        $this->table(
            ['Key', 'Tier', 'Status', 'Max Users', 'Max Agents', 'Licensee', 'Email', 'Activated', 'Expires'],
            $rows,
        );

        return self::SUCCESS;
    }
}
