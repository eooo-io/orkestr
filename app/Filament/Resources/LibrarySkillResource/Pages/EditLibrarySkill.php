<?php

namespace App\Filament\Resources\LibrarySkillResource\Pages;

use App\Filament\Resources\LibrarySkillResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLibrarySkill extends EditRecord
{
    protected static string $resource = LibrarySkillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
