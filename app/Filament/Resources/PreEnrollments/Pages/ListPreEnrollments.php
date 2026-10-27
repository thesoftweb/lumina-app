<?php

namespace App\Filament\Resources\PreEnrollments\Pages;

use App\Enums\PreEnrollmentStatus;
use App\Filament\Resources\PreEnrollments\Actions\PreEnrollmentActions;
use App\Filament\Resources\PreEnrollments\PreEnrollmentResource;
use App\Models\PreEnrollment;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPreEnrollments extends ListRecords
{
    protected static string $resource = PreEnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PreEnrollmentActions::generateReenrollments(),
            CreateAction::make()->label('Registrar pré-matrícula'),
        ];
    }

    /** As contagens das abas seguem o ano letivo escolhido no filtro, como a tabela. */
    public function getTabs(): array
    {
        $tabs = [
            'abertas' => Tab::make('Abertas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->open())
                ->badge(fn (): int => $this->countInSelectedYear(fn (Builder $query) => $query->open()))
                ->badgeColor('info'),
        ];

        foreach (PreEnrollmentStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status === PreEnrollmentStatus::New ? 'Novas' : $status->getLabel())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status->value))
                ->badge(fn (): int => $this->countInSelectedYear(fn (Builder $query) => $query->where('status', $status->value)))
                ->badgeColor($status->getColor());
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'abertas';
    }

    private function countInSelectedYear(callable $scope): int
    {
        $yearId = $this->tableFilters['academic_year_id']['value'] ?? PreEnrollmentResource::defaultAcademicYearId();

        return $scope(PreEnrollment::query()->when($yearId, fn (Builder $query) => $query->where('academic_year_id', $yearId)))->count();
    }
}
