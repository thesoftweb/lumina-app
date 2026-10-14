<?php

namespace App\Filament\Resources\Enrollments\Pages;

use App\Filament\Resources\Enrollments\Actions\ManagesEnrollmentActions;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEnrollment extends EditRecord
{
    use ManagesEnrollmentActions;

    protected static string $resource = EnrollmentResource::class;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    public function getSubheading(): ?string
    {
        return 'Atualize os dados da matrícula e acesse documentos e cobranças.';
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Salvar alterações');
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make($this->getEnrollmentActions())
                ->label('Documentos e cobranças')
                ->icon('heroicon-o-ellipsis-horizontal'),
            DeleteAction::make(),
        ];
    }
}
