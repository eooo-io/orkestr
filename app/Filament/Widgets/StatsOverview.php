<?php

namespace App\Filament\Widgets;

use App\Models\Agent;
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
        ];
    }
}
