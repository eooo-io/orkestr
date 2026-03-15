<?php

namespace App\Filament\Resources\ContentPolicyResource\Pages;

use App\Filament\Resources\ContentPolicyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContentPolicy extends EditRecord
{
    protected static string $resource = ContentPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
