<?php

namespace App\Console\Commands;

use App\Services\HealthCheckService;
use Illuminate\Console\Command;

class HealthCheckCommand extends Command
{
    protected $signature = 'orkestr:health';

    protected $description = 'Run all health diagnostics and display results';

    public function handle(HealthCheckService $service): int
    {
        $this->info('Running health checks...');
        $this->newLine();

        $checks = $service->runAll();
        $systemInfo = $service->systemInfo();

        $rows = [];
        $hasUnhealthy = false;

        foreach ($checks as $name => $result) {
            $status = $result['status'];
            $statusDisplay = match ($status) {
                'healthy', 'configured' => "<fg=green>{$status}</>",
                'degraded' => "<fg=yellow>{$status}</>",
                'unhealthy', 'unreachable' => "<fg=red>{$status}</>",
                'not_configured' => "<fg=gray>{$status}</>",
                default => $status,
            };

            $latency = $result['latency_ms'] !== null ? $result['latency_ms'].'ms' : '-';

            $rows[] = [ucfirst($name), $statusDisplay, $result['message'], $latency];

            if ($status === 'unhealthy') {
                $hasUnhealthy = true;
            }
        }

        $this->table(['Check', 'Status', 'Message', 'Latency'], $rows);

        $this->newLine();
        $this->info('System Information:');
        $this->table(['Key', 'Value'], collect($systemInfo)->map(fn ($v, $k) => [
            str_replace('_', ' ', ucfirst($k)),
            is_bool($v) ? ($v ? 'Yes' : 'No') : (string) $v,
        ])->values()->toArray());

        if ($hasUnhealthy) {
            $this->newLine();
            $this->error('One or more checks are unhealthy!');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('All checks passed.');

        return self::SUCCESS;
    }
}
