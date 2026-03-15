<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LibrarySkillResource\Pages;
use App\Models\LibrarySkill;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class LibrarySkillResource extends Resource
{
    protected static ?string $model = LibrarySkill::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Library Skills';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
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

                Forms\Components\Textarea::make('description')
                    ->rows(2)
                    ->maxLength(1000),

                Forms\Components\Select::make('category')
                    ->options([
                        'Laravel' => 'Laravel',
                        'PHP' => 'PHP',
                        'TypeScript' => 'TypeScript',
                        'FinTech' => 'FinTech',
                        'DevOps' => 'DevOps',
                        'Writing' => 'Writing',
                    ]),

                Forms\Components\TagsInput::make('tags'),

                Forms\Components\Select::make('model')
                    ->label('Model')
                    ->options([
                        'claude-sonnet-4-6' => 'Claude Sonnet 4.6',
                        'claude-opus-4-6' => 'Claude Opus 4.6',
                        'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5',
                    ])
                    ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                        $set('frontmatter.model', $state);
                    }),

                Forms\Components\Textarea::make('body')
                    ->required()
                    ->rows(20)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tags')
                    ->badge()
                    ->separator(',')
                    ->getStateUsing(fn (LibrarySkill $record) => $record->tags ?? []),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'Laravel' => 'Laravel',
                        'PHP' => 'PHP',
                        'TypeScript' => 'TypeScript',
                        'FinTech' => 'FinTech',
                        'DevOps' => 'DevOps',
                        'Writing' => 'Writing',
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
            'index' => Pages\ListLibrarySkills::route('/'),
            'create' => Pages\CreateLibrarySkill::route('/create'),
            'edit' => Pages\EditLibrarySkill::route('/{record}/edit'),
        ];
    }
}
