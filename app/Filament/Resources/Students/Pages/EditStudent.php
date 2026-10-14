<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStudent extends EditRecord
{
    protected static string $resource = StudentResource::class;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    public function getSubheading(): ?string
    {
        return 'Atualize o cadastro, os responsáveis e as informações de acompanhamento.';
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Salvar alterações');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createEnrollment')
                ->label('Criar matrícula')
                ->icon('heroicon-o-document-plus')
                ->url(fn (): string => route('filament.admin.resources.enrollments.create', ['student_id' => $this->record->id])),
            DeleteAction::make(),
        ];
    }
}
