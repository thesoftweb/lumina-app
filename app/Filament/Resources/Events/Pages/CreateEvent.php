<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set company_id if available from auth
        if (auth()->user()->hasRole('admin') || auth()->user()->hasRole('coordinator')) {
            $data['company_id'] = auth()->user()->company_id ?? 1;
        }

        return $data;
    }
}
