<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Models\ProjectProvider;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $providerSlugs = $this->data['provider_slugs'] ?? [];

        // Sync providers: remove old, add new
        $this->record->providers()->delete();

        foreach ($providerSlugs as $slug) {
            ProjectProvider::create([
                'project_id' => $this->record->id,
                'provider_slug' => $slug,
            ]);
        }
    }
}
