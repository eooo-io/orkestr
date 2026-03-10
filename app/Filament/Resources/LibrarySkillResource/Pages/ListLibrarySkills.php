<?php

namespace App\Filament\Resources\LibrarySkillResource\Pages;

use App\Filament\Resources\LibrarySkillResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLibrarySkills extends ListRecords
{
    protected static string $resource = LibrarySkillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
