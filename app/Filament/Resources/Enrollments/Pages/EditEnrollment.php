<?php

namespace App\Filament\Resources\Enrollments\Pages;

use App\Filament\Resources\Enrollments\Actions\ManagesEnrollmentActions;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Filament\Resources\Enrollments\Schemas\EnrollmentFormFields;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

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

    protected function onValidationError(ValidationException $exception): void
    {
        EnrollmentFormFields::notifyIfBlocked($exception);
    }

    protected function getHeaderActions(): array
    {
        return [
            // O cabeçalho do Filament não quebra linha: no celular as ações mostram só o ícone.
            ViewAction::make()->label('Visualizar')->icon('heroicon-o-eye')->color('gray')->labeledFrom('md'),
            $this->createEnrollmentSheetAction()->color('gray')->labeledFrom('md'),
            ActionGroup::make($this->getEnrollmentActions())
                ->label('Documentos e cobranças')
                ->icon('heroicon-o-ellipsis-horizontal'),
            DeleteAction::make()->icon('heroicon-o-trash')->labeledFrom('md'),
        ];
    }
}
