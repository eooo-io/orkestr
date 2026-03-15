<?php

namespace App\Filament\Widgets;

use App\Models\Agent;
use App\Models\ExecutionRun;
use App\Models\LibrarySkill;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $completed7d = ExecutionRun::where('created_at', '>=', now()->subDays(7))
            ->where('status', 'completed')
            ->count();
        $totalFinished7d = ExecutionRun::where('created_at', '>=', now()->subDays(7))
            ->whereIn('status', ['completed', 'failed'])
            ->count();
        $successRate = $totalFinished7d > 0
            ? round(($completed7d / $totalFinished7d) * 100)
            : 0;

        return [
            Stat::make('Users', User::count())
                ->icon('heroicon-o-users'),
            Stat::make('Organizations', Organization::count())
                ->icon('heroicon-o-building-office'),
            Stat::make('Projects', Project::count())
                ->icon('heroicon-o-folder-open'),
            Stat::make('Agents', Agent::count())
                ->icon('heroicon-o-cpu-chip'),
            Stat::make('Library Skills', LibrarySkill::count())
                ->icon('heroicon-o-book-open'),
            Stat::make('Execution Runs', ExecutionRun::count())
                ->icon('heroicon-o-play'),
            Stat::make('Runs (7d)', ExecutionRun::where('created_at', '>=', now()->subDays(7))->count())
                ->icon('heroicon-o-chart-bar')
                ->description($successRate . '% success rate'),
            Stat::make('Total Tokens', number_format(ExecutionRun::sum('total_tokens')))
                ->icon('heroicon-o-calculator'),
        ];
    }
}
