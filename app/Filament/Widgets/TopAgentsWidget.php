<?php

namespace App\Filament\Widgets;

use App\Models\Agent;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopAgentsWidget extends BaseWidget
{
    protected static ?string $heading = 'Top Agents by Executions';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Agent::withCount('executionRuns')
                    ->orderByDesc('execution_runs_count')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->color('gray'),
                Tables\Columns\TextColumn::make('execution_runs_count')
                    ->label('Total Runs')
                    ->sortable(),
                Tables\Columns\TextColumn::make('model')
                    ->badge(),
            ])
            ->paginated(false);
    }
}
