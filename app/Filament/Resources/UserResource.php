<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Profile')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                            ]),

                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn (?string $state) => $state ? Hash::make($state) : null)
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave blank to keep current password' : null),
                    ]),

                Forms\Components\Section::make('Organization')
                    ->schema([
                        Forms\Components\Select::make('current_organization_id')
                            ->label('Current Organization')
                            ->relationship('currentOrganization', 'name')
                            ->searchable()
                            ->preload(),
                    ]),

                Forms\Components\Section::make('Auth Provider')
                    ->schema([
                        Forms\Components\Placeholder::make('auth_provider_info')
                            ->label('Auth Provider')
                            ->content(fn (?User $record) => $record?->auth_provider ?? 'email'),

                        Forms\Components\Placeholder::make('github_id_info')
                            ->label('GitHub ID')
                            ->content(fn (?User $record) => $record?->github_id ?? '—')
                            ->visible(fn (?User $record) => $record?->github_id !== null),

                        Forms\Components\Placeholder::make('apple_id_info')
                            ->label('Apple ID')
                            ->content(fn (?User $record) => $record?->apple_id ?? '—')
                            ->visible(fn (?User $record) => $record?->apple_id !== null),
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

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('auth_provider')
                    ->label('Auth')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'github' => 'success',
                        'apple' => 'gray',
                        default => 'info',
                    })
                    ->default('email'),

                Tables\Columns\TextColumn::make('currentOrganization.name')
                    ->label('Organization')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('organizations_count')
                    ->counts('organizations')
                    ->label('Orgs')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('email_verified_at')
                    ->label('Verified')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('auth_provider')
                    ->options([
                        'email' => 'Email',
                        'github' => 'GitHub',
                        'apple' => 'Apple',
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
