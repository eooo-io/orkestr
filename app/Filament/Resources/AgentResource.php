<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AgentResource\Pages;
use App\Models\Agent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AgentResource extends Resource
{
    protected static ?string $model = Agent::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationLabel = 'Agents';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Agent')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Identity')
                            ->icon('heroicon-o-identification')
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

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('role')
                                            ->required()
                                            ->maxLength(255)
                                            ->helperText('Short identifier, e.g. "code-reviewer"'),

                                        Forms\Components\Select::make('model')
                                            ->options([
                                                'claude-sonnet-4-6' => 'Claude Sonnet 4.6',
                                                'claude-opus-4-6' => 'Claude Opus 4.6',
                                                'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5',
                                                'gpt-4o' => 'GPT-4o',
                                                'gpt-4o-mini' => 'GPT-4o Mini',
                                                'o3' => 'o3',
                                                'gemini-2.5-pro' => 'Gemini 2.5 Pro',
                                                'gemini-2.5-flash' => 'Gemini 2.5 Flash',
                                            ])
                                            ->default('claude-sonnet-4-6'),
                                    ]),

                                Forms\Components\Textarea::make('description')
                                    ->rows(2)
                                    ->maxLength(1000)
                                    ->columnSpanFull(),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('icon')
                                            ->options([
                                                'brain' => 'Brain',
                                                'clipboard-list' => 'Clipboard',
                                                'code' => 'Code',
                                                'shield-check' => 'Shield',
                                                'wrench' => 'Wrench',
                                                'search' => 'Search',
                                                'users' => 'Users',
                                                'layout' => 'Layout',
                                                'server' => 'Server',
                                                'git-branch' => 'Git Branch',
                                                'lock' => 'Lock',
                                                'zap' => 'Zap',
                                            ])
                                            ->default('brain'),

                                        Forms\Components\TextInput::make('sort_order')
                                            ->numeric()
                                            ->default(0),
                                    ]),

                                Forms\Components\Toggle::make('is_template')
                                    ->label('Template Agent')
                                    ->helperText('Template agents are available as defaults for all users')
                                    ->default(true),
                            ]),

                        Forms\Components\Tabs\Tab::make('Instructions')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Forms\Components\Textarea::make('base_instructions')
                                    ->label('Base Instructions')
                                    ->rows(12)
                                    ->helperText('Core prompt that defines what this agent does')
                                    ->columnSpanFull(),

                                Forms\Components\Textarea::make('persona_prompt')
                                    ->label('Persona Prompt')
                                    ->rows(4)
                                    ->helperText('Optional persona overlay')
                                    ->columnSpanFull(),

                                Forms\Components\Textarea::make('system_prompt')
                                    ->label('System Prompt Override')
                                    ->rows(4)
                                    ->helperText('Explicit system prompt (overrides composed prompt)')
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Goal')
                            ->icon('heroicon-o-flag')
                            ->schema([
                                Forms\Components\Textarea::make('objective_template')
                                    ->label('Objective')
                                    ->rows(4)
                                    ->columnSpanFull(),

                                Forms\Components\TagsInput::make('success_criteria')
                                    ->label('Success Criteria')
                                    ->helperText('e.g. all_tests_passing, no_security_issues'),

                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('max_iterations')
                                            ->numeric()
                                            ->default(10)
                                            ->minValue(1)
                                            ->maxValue(1000),

                                        Forms\Components\TextInput::make('timeout_seconds')
                                            ->numeric()
                                            ->default(300)
                                            ->suffix('seconds'),

                                        Forms\Components\Select::make('loop_condition')
                                            ->options([
                                                'goal_met' => 'Goal Met',
                                                'max_iterations' => 'Max Iterations',
                                                'timeout' => 'Timeout',
                                                'manual' => 'Manual',
                                            ])
                                            ->default('goal_met'),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Reasoning')
                            ->icon('heroicon-o-light-bulb')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('planning_mode')
                                            ->options([
                                                'none' => 'None',
                                                'act' => 'Act',
                                                'plan_then_act' => 'Plan then Act',
                                                'react' => 'ReAct',
                                            ])
                                            ->default('react'),

                                        Forms\Components\TextInput::make('temperature')
                                            ->numeric()
                                            ->default(0.7)
                                            ->minValue(0)
                                            ->maxValue(2)
                                            ->step(0.1),
                                    ]),

                                Forms\Components\Select::make('context_strategy')
                                    ->options([
                                        'full' => 'Full Context',
                                        'summary' => 'Summary',
                                        'sliding_window' => 'Sliding Window',
                                        'rag' => 'RAG',
                                    ])
                                    ->default('full'),
                            ]),

                        Forms\Components\Tabs\Tab::make('Autonomy')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Forms\Components\Select::make('autonomy_level')
                                    ->options([
                                        'supervised' => 'Supervised',
                                        'semi_autonomous' => 'Semi-Autonomous',
                                        'autonomous' => 'Autonomous',
                                    ])
                                    ->default('supervised'),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('budget_limit_usd')
                                            ->label('Per-Run Budget (USD)')
                                            ->numeric()
                                            ->prefix('$'),

                                        Forms\Components\TextInput::make('daily_budget_limit_usd')
                                            ->label('Daily Budget (USD)')
                                            ->numeric()
                                            ->prefix('$'),
                                    ]),

                                Forms\Components\Toggle::make('can_delegate')
                                    ->label('Can Delegate to Other Agents')
                                    ->default(false),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Agent $record) => Str::limit($record->description, 60)),

                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\TextColumn::make('model')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('planning_mode')
                    ->label('Reasoning')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'react' => 'success',
                        'plan_then_act' => 'warning',
                        'act' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_template')
                    ->label('Template')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_template')
                    ->label('Template'),
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
            'index' => Pages\ListAgents::route('/'),
            'create' => Pages\CreateAgent::route('/create'),
            'edit' => Pages\EditAgent::route('/{record}/edit'),
        ];
    }
}
