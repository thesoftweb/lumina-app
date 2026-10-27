<?php

namespace App\Filament\Resources\PreEnrollments\Pages;

use App\Filament\Resources\PreEnrollments\PreEnrollmentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPreEnrollment extends EditRecord
{
    protected static string $resource = PreEnrollmentResource::class;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()->hidden(fn (): bool => $this->record->isConverted()),
        ];
    }

    /** Corrigir nome, nascimento ou CPF pode revelar que a criança já estuda na escola. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->isConverted()) {
            return $data;
        }

        return [...$data, ...PreEnrollmentResource::classify((clone $this->record)->fill($data))];
    }

    protected function getRedirectUrl(): ?string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
