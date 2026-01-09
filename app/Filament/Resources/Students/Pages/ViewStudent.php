<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewStudent extends ViewRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            EditAction::make(),
            Action::make('createEnrollment')
                ->label('Criar Matrícula')
                ->icon('heroicon-o-document-plus')
                ->requiresConfirmation()
                ->modalHeading('Criar Matrícula')
                ->modalDescription('Deseja criar uma matrícula para este aluno agora?')
                ->modalSubmitActionLabel('Criar Matrícula')
                ->modalCancelActionLabel('Depois')
                ->action(function () {
                    session()->forget('new_student_id');
                    redirect(route('filament.admin.resources.enrollments.create', ['student_id' => $this->record->id]));
                })
        ];

        return $actions;
    }
}
