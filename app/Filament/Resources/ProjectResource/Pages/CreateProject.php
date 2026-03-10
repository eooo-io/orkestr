<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Models\ProjectProvider;
use Filament\Resources\Pages\CreateRecord;

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;

    protected function afterCreate(): void
    {
        $providerSlugs = $this->data['provider_slugs'] ?? [];

        foreach ($providerSlugs as $slug) {
            ProjectProvider::create([
                'project_id' => $this->record->id,
                'provider_slug' => $slug,
            ]);
        }
    }
}
