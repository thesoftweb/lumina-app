<?php

namespace App\Filament\Resources\Enrollments\Pages;

use App\Filament\Resources\Enrollments\Actions\ManagesEnrollmentActions;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEnrollment extends ViewRecord
{
    use ManagesEnrollmentActions;

    protected static string $resource = EnrollmentResource::class;

    public function getTitle(): string
    {
        return $this->record->student?->name ?? 'Matrícula';
    }

    public function getSubheading(): ?string
    {
        return "Matrícula nº {$this->record->number()}";
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->createEnrollmentSheetAction(),
            // O cabeçalho do Filament não quebra linha: no celular as ações secundárias mostram só o ícone.
            EditAction::make()->icon('heroicon-o-pencil-square')->color('gray')->labeledFrom('md'),
            ActionGroup::make($this->getEnrollmentActions())
                ->label('Documentos e cobranças')
                ->icon('heroicon-o-ellipsis-horizontal')
                ->button()
                ->labeledFrom('md')
                ->color('gray'),
        ];
    }
}
