<?php

namespace App\Filament\Resources\AcademicYears\Pages;

use App\Filament\Resources\AcademicYears\AcademicYearResource;
use App\Filament\Resources\AcademicYears\Schemas\AcademicYearForm;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Services\Academic\ClassroomYearCopier;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class ViewAcademicYear extends ViewRecord
{
    protected static string $resource = AcademicYearResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openPublicForm')
                ->label('Abrir ficha pública')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): ?string => AcademicYearForm::publicLink($this->record), shouldOpenInNewTab: true)
                ->visible(fn (): bool => filled(AcademicYearForm::publicLink($this->record))),
            $this->copyClassroomsAction(),
            EditAction::make(),
        ];
    }

    /** Monta as turmas deste ano copiando as de outro ano; as turmas de origem não são alteradas. */
    private function copyClassroomsAction(): Action
    {
        return Action::make('copyClassrooms')
            ->label('Copiar turmas de outro ano')
            ->icon('heroicon-o-document-duplicate')
            ->color('gray')
            ->authorize(fn (): bool => (bool) auth()->user()?->can('create', Classroom::class))
            ->modalHeading(fn (): string => 'Copiar turmas para '.$this->record->description)
            ->modalDescription('Copia nome, nível, série, dias letivos, modo de avaliação e disciplinas. Os planos são os deste ano com o mesmo nome. Professores não são copiados. As turmas de origem não mudam, e turmas com o mesmo nome neste ano são mantidas.')
            ->fillForm(fn (): array => ['source_id' => $source = $this->defaultSourceId(), 'classroom_ids' => $this->sourceClassrooms($source)->keys()->all()])
            ->schema([
                Select::make('source_id')->label('Copiar do ano')
                    ->options(fn (): array => AcademicYear::query()->whereKeyNot($this->record->getKey())->orderByDesc('year')->pluck('description', 'id')->all())
                    ->required()->live()
                    ->afterStateUpdated(fn (Set $set, $state) => $set('classroom_ids', $this->sourceClassrooms($state)->keys()->all())),
                CheckboxList::make('classroom_ids')->label('Turmas')
                    ->options(fn (Get $get): array => $this->sourceClassrooms($get('source_id'))->all())
                    ->columns(2)->bulkToggleable()->required(),
            ])
            ->action(function (array $data): void {
                $result = app(ClassroomYearCopier::class)->copy(AcademicYear::query()->findOrFail($data['source_id']), $this->record, array_map('intval', $data['classroom_ids']));

                Notification::make()->success()
                    ->title($result['created']->count().' turma(s) criada(s) em '.$this->record->description)
                    ->body(collect([
                        $result['created']->pluck('name')->implode(', '),
                        $result['skipped'] ? 'Já existiam: '.implode(', ', $result['skipped']).'.' : null,
                        $result['missing_plans'] ? 'Sem plano equivalente neste ano (não vinculado): '.implode('; ', $result['missing_plans']).'.' : null,
                    ])->filter()->implode("\n"))
                    ->persistent()
                    ->send();
            });
    }

    private function defaultSourceId(): ?int
    {
        return AcademicYear::query()->whereKeyNot($this->record->getKey())->where('year', '<', $this->record->year)->orderByDesc('year')->value('id');
    }

    private function sourceClassrooms(mixed $sourceId)
    {
        return filled($sourceId) ? Classroom::query()->where('academic_year_id', $sourceId)->pluck('name', 'id') : collect();
    }
}
