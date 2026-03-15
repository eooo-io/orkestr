<?php

namespace App\Filament\Widgets;

use App\Services\HealthCheckService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class HealthCheckWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected function getStats(): array
    {
        $service = app(HealthCheckService::class);
        $checks = $service->runAll();

        $dbCheck = $checks['database'];
        $cacheCheck = $checks['cache'];
        $storageCheck = $checks['storage'];

        // Count configured LLM providers
        $providers = ['anthropic', 'openai', 'ollama'];
        $configured = collect($providers)
            ->filter(fn ($p) => in_array($checks[$p]['status'], ['healthy', 'configured']))
            ->count();

        return [
            Stat::make('Database', ucfirst($dbCheck['status']))
                ->icon('heroicon-o-circle-stack')
                ->description($dbCheck['latency_ms'] !== null ? $dbCheck['latency_ms'].'ms latency' : $dbCheck['message'])
                ->color($this->statusColor($dbCheck['status'])),

            Stat::make('Cache', ucfirst($cacheCheck['status']))
                ->icon('heroicon-o-bolt')
                ->description($cacheCheck['latency_ms'] !== null ? $cacheCheck['latency_ms'].'ms latency' : $cacheCheck['message'])
                ->color($this->statusColor($cacheCheck['status'])),

            Stat::make('Storage', ucfirst($storageCheck['status']))
                ->icon('heroicon-o-server')
                ->description($storageCheck['message'])
                ->color($this->statusColor($storageCheck['status'])),

            Stat::make('LLM Providers', "{$configured}/".count($providers).' configured')
                ->icon('heroicon-o-cpu-chip')
                ->description($configured === count($providers) ? 'All providers ready' : 'Some providers not configured')
                ->color($configured === count($providers) ? 'success' : ($configured > 0 ? 'warning' : 'danger')),
        ];
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'healthy' => 'success',
            'degraded' => 'warning',
            'unhealthy', 'unreachable' => 'danger',
            default => 'gray',
        };
    }
}
