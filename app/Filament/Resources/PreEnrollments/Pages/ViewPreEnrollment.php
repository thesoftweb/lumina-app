<?php

namespace App\Filament\Resources\PreEnrollments\Pages;

use App\Filament\Resources\PreEnrollments\Actions\PreEnrollmentActions;
use App\Filament\Resources\PreEnrollments\PreEnrollmentResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPreEnrollment extends ViewRecord
{
    protected static string $resource = PreEnrollmentResource::class;

    public function getTitle(): string
    {
        return $this->record->student_name;
    }

    public function getSubheading(): ?string
    {
        return "Pré-matrícula {$this->record->code} · {$this->record->academicYear->description}";
    }

    protected function getHeaderActions(): array
    {
        return [
            PreEnrollmentActions::convert(),
            PreEnrollmentActions::whatsApp()->labeledFrom('md'),
            // O cabeçalho não quebra linha: no celular as ações secundárias ficam no menu
            ActionGroup::make([
                PreEnrollmentActions::sendReenrollmentLink(),
                PreEnrollmentActions::copyReenrollmentLink(),
                PreEnrollmentActions::registerContact(),
                PreEnrollmentActions::markLost(),
                EditAction::make(),
            ])
                ->label('Mais')
                ->icon('heroicon-o-ellipsis-horizontal')
                ->button()
                ->labeledFrom('md')
                ->color('gray'),
        ];
    }
}
