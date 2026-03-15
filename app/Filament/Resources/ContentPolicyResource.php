<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContentPolicyResource\Pages;
use App\Models\ContentPolicy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContentPolicyResource extends Resource
{
    protected static ?string $model = ContentPolicy::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Security';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Policy Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->maxLength(1000),

                        Forms\Components\Select::make('organization_id')
                            ->relationship('organization', 'name')
                            ->required()
                            ->searchable(),

                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                    ]),

                Forms\Components\Section::make('Rules')
                    ->schema([
                        Forms\Components\Repeater::make('rules')
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->options([
                                        'block_secrets' => 'Block Secrets / Credentials',
                                        'block_dangerous_commands' => 'Block Dangerous Commands',
                                        'block_data_exfiltration' => 'Block Data Exfiltration',
                                        'block_prompt_injection' => 'Block Prompt Injection',
                                        'require_output_format' => 'Require Output Format',
                                        'max_token_limit' => 'Max Token Limit',
                                        'custom_pattern' => 'Custom Pattern',
                                    ])
                                    ->required(),

                                Forms\Components\Select::make('action')
                                    ->options([
                                        'block' => 'Block (prevent save)',
                                        'warn' => 'Warn (allow save)',
                                    ])
                                    ->default('warn')
                                    ->required(),

                                Forms\Components\TextInput::make('value')
                                    ->label('Value (e.g. max tokens)')
                                    ->numeric()
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'max_token_limit'),

                                Forms\Components\TextInput::make('pattern')
                                    ->label('Regex Pattern')
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'custom_pattern'),

                                Forms\Components\TextInput::make('message')
                                    ->label('Custom Message')
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'custom_pattern'),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('organization.name')
                    ->sortable(),

                Tables\Columns\TextColumn::make('rules')
                    ->label('Rules')
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state) . ' rules' : '0 rules'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContentPolicies::route('/'),
            'create' => Pages\CreateContentPolicy::route('/create'),
            'edit' => Pages\EditContentPolicy::route('/{record}/edit'),
        ];
    }
}
