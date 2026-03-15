<?php

namespace App\Filament\Resources\ContentPolicyResource\Pages;

use App\Filament\Resources\ContentPolicyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContentPolicies extends ListRecords
{
    protected static string $resource = ContentPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
