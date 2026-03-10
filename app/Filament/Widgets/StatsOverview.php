<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use App\Models\Skill;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $lastSynced = Project::whereNotNull('synced_at')
            ->latest('synced_at')
            ->value('synced_at');

        return [
            Stat::make('Total Projects', Project::count()),
            Stat::make('Total Skills', Skill::count()),
            Stat::make('Last Synced', $lastSynced ? $lastSynced->diffForHumans() : 'Never'),
        ];
    }
}
