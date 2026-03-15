<?php

namespace App\Filament\Widgets;

use App\Models\ExecutionRun;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class ExecutionChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Agent Executions (30 Days)';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $days = collect(range(29, 0))->map(fn ($i) => Carbon::today()->subDays($i));

        $runs = ExecutionRun::where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, status, COUNT(*) as count')
            ->groupBy('date', 'status')
            ->get()
            ->groupBy('date');

        $completed = [];
        $failed = [];
        $labels = [];

        foreach ($days as $day) {
            $dateStr = $day->toDateString();
            $labels[] = $day->format('M d');
            $dayData = $runs->get($dateStr, collect());
            $completed[] = $dayData->where('status', 'completed')->sum('count');
            $failed[] = $dayData->where('status', 'failed')->sum('count');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Completed',
                    'data' => $completed,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                ],
                [
                    'label' => 'Failed',
                    'data' => $failed,
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
