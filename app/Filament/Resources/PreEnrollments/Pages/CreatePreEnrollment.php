<?php

namespace App\Filament\Resources\PreEnrollments\Pages;

use App\Enums\PreEnrollmentStatus;
use App\Filament\Resources\PreEnrollments\PreEnrollmentResource;
use App\Models\PreEnrollment;
use Filament\Resources\Pages\CreateRecord;

/** Ficha registrada pela secretaria (família que ligou ou foi à escola), mesmo com a ficha pública fechada. */
class CreatePreEnrollment extends CreateRecord
{
    protected static string $resource = PreEnrollmentResource::class;

    public function getSubheading(): ?string
    {
        return 'Para famílias que procuraram a escola por telefone ou pessoalmente.';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] ??= PreEnrollmentStatus::New->value;

        return [...$data, ...PreEnrollmentResource::classify(new PreEnrollment($data))];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
