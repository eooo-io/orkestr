<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Jobs\ProjectScanJob;
use App\Models\Project;
use App\Services\ProviderSyncService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Textarea::make('description')
                    ->rows(3)
                    ->maxLength(1000),

                Forms\Components\TextInput::make('path')
                    ->required()
                    ->maxLength(500)
                    ->helperText('Absolute path to the project directory on the server'),

                Forms\Components\CheckboxList::make('provider_slugs')
                    ->label('Providers')
                    ->options([
                        'claude' => 'Claude',
                        'cursor' => 'Cursor',
                        'copilot' => 'GitHub Copilot',
                        'windsurf' => 'Windsurf',
                        'cline' => 'Cline',
                        'openai' => 'OpenAI',
                    ])
                    ->columns(3)
                    ->afterStateHydrated(function (Forms\Components\CheckboxList $component, ?Project $record) {
                        if ($record) {
                            $component->state($record->providers->pluck('provider_slug')->toArray());
                        }
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('path')
                    ->limit(40)
                    ->tooltip(fn (Project $record) => $record->path),

                Tables\Columns\TextColumn::make('providers')
                    ->label('Providers')
                    ->badge()
                    ->getStateUsing(fn (Project $record) => $record->providers->pluck('provider_slug')->toArray()),

                Tables\Columns\TextColumn::make('skills_count')
                    ->counts('skills')
                    ->label('Skills')
                    ->sortable(),

                Tables\Columns\TextColumn::make('synced_at')
                    ->label('Last Synced')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('scan')
                    ->label('Scan')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (Project $record) {
                        ProjectScanJob::dispatch($record);
                        Notification::make()
                            ->title('Scan started')
                            ->body("Scanning {$record->name}...")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('sync')
                    ->label('Sync')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->action(function (Project $record) {
                        app(ProviderSyncService::class)->syncProject($record);
                        Notification::make()
                            ->title('Sync complete')
                            ->body("Synced {$record->name} to all providers")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('open_editor')
                    ->label('Editor')
                    ->icon('heroicon-o-code-bracket')
                    ->url(fn (Project $record) => "http://localhost:5173/projects/{$record->id}")
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}
