<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrganizationResource\Pages;
use App\Models\Organization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Organization Details')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),

                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Plan & Billing')
                    ->schema([
                        Forms\Components\Select::make('plan')
                            ->options([
                                'free' => 'Free',
                                'pro' => 'Pro',
                                'teams' => 'Teams',
                            ])
                            ->default('free')
                            ->required(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DateTimePicker::make('trial_ends_at')
                                    ->label('Trial Ends'),

                                Forms\Components\DateTimePicker::make('subscription_ends_at')
                                    ->label('Subscription Ends'),
                            ]),

                        Forms\Components\KeyValue::make('plan_limits')
                            ->label('Plan Limits')
                            ->helperText('Custom overrides for plan limits (JSON key-value pairs)'),
                    ]),

                Forms\Components\Section::make('Members')
                    ->schema([
                        Forms\Components\Placeholder::make('members_count')
                            ->label('Total Members')
                            ->content(fn (?Organization $record) => $record?->users()->count() ?? 0),

                        Forms\Components\Placeholder::make('owner_name')
                            ->label('Owner')
                            ->content(fn (?Organization $record) => $record?->owner()?->name ?? '—'),

                        Forms\Components\Placeholder::make('members_list')
                            ->label('Members')
                            ->content(function (?Organization $record) {
                                if (! $record) {
                                    return '—';
                                }

                                return $record->users()
                                    ->get()
                                    ->map(fn ($u) => "{$u->name} ({$u->pivot->role})")
                                    ->join(', ') ?: '—';
                            }),
                    ])
                    ->visible(fn (string $operation) => $operation === 'edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('plan')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'teams' => 'success',
                        'pro' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Members')
                    ->sortable(),

                Tables\Columns\TextColumn::make('projects_count')
                    ->counts('projects')
                    ->label('Projects')
                    ->sortable(),

                Tables\Columns\TextColumn::make('subscription_ends_at')
                    ->label('Subscription')
                    ->since()
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('plan')
                    ->options([
                        'free' => 'Free',
                        'pro' => 'Pro',
                        'teams' => 'Teams',
                    ]),
            ])
            ->actions([
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
            'index' => Pages\ListOrganizations::route('/'),
            'create' => Pages\CreateOrganization::route('/create'),
            'edit' => Pages\EditOrganization::route('/{record}/edit'),
        ];
    }
}
