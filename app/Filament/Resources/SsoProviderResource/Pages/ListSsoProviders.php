<?php

namespace App\Filament\Resources\SsoProviderResource\Pages;

use App\Filament\Resources\SsoProviderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSsoProviders extends ListRecords
{
    protected static string $resource = SsoProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
