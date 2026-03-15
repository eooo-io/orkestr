<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class LicenseGenerateCommand extends Command
{
    protected $signature = 'orkestr:license:generate
                            {tier : License tier (self_hosted, enterprise)}
                            {--licensee= : Licensee name}
                            {--email= : Licensee email}
                            {--max-users=0 : Maximum users (0 = unlimited)}
                            {--max-agents=0 : Maximum agents (0 = unlimited)}
                            {--expires= : Expiration date (Y-m-d)}';

    protected $description = 'Generate a new Orkestr license key';

    public function handle(LicenseService $licenseService): int
    {
        $tier = $this->argument('tier');

        if (! in_array($tier, ['self_hosted', 'enterprise'])) {
            $this->error('Invalid tier. Must be "self_hosted" or "enterprise".');

            return self::FAILURE;
        }

        $options = [
            'licensee_name' => $this->option('licensee'),
            'licensee_email' => $this->option('email'),
            'max_users' => (int) $this->option('max-users'),
            'max_agents' => (int) $this->option('max-agents'),
        ];

        if ($expires = $this->option('expires')) {
            try {
                $options['expires_at'] = Carbon::parse($expires)->endOfDay();
            } catch (\Exception $e) {
                $this->error('Invalid expiration date format. Use Y-m-d.');

                return self::FAILURE;
            }
        }

        $license = $licenseService->generate($tier, $options);

        $this->newLine();
        $this->info('License key generated successfully!');
        $this->newLine();
        $this->line("  Key:        <fg=green;options=bold>{$license->key}</>");
        $this->line("  Tier:       {$license->tier}");
        $this->line('  Max Users:  ' . ($license->max_users === 0 ? 'Unlimited' : $license->max_users));
        $this->line('  Max Agents: ' . ($license->max_agents === 0 ? 'Unlimited' : $license->max_agents));
        $this->line('  Expires:    ' . ($license->expires_at ? $license->expires_at->toDateString() : 'Never'));

        if ($license->licensee_name) {
            $this->line("  Licensee:   {$license->licensee_name}");
        }
        if ($license->licensee_email) {
            $this->line("  Email:      {$license->licensee_email}");
        }

        $this->newLine();
        $this->line('Features: ' . implode(', ', $license->features ?? []));
        $this->newLine();

        return self::SUCCESS;
    }
}
