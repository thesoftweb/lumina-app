<?php

namespace App\Filament\Resources\AgendaResource\Pages;

use App\Filament\Resources\AgendaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAgenda extends CreateRecord
{
    protected static string $resource = AgendaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = auth()->user()->company_id ?? 1;
        $data['classroom_id'] = null;

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->record->global) {
            $this->record->classrooms()->detach();
        }
    }
}
