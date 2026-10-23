<?php

namespace App\Filament\Resources\Enrollments\RelationManagers;

use App\Enums\AssessmentMode;
use App\Models\ClassroomSubject;
use App\Models\ConceptScale;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeChange;
use App\Models\Term;
use App\Services\GradeCorrection;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Notas da matrícula para a secretaria: consulta, lançamento e correção (inclusive em bimestre
 * fechado). Toda alteração exige motivo e fica registrada. Professores lançam pelo diário.
 */
class GradeRelationManager extends RelationManager
{
    protected static string $relationship = 'grade';

    protected static ?string $title = 'Notas';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-academic-cap';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['secretary', 'admin']);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $numeric = $this->mode() === AssessmentMode::Numeric;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['classroomSubject.subject', 'term', 'teacher', 'conceptScale'])
                ->withCount('changes')
                ->leftJoin('terms', 'terms.id', '=', 'grades.term_id')
                ->select('grades.*')
                ->orderBy('terms.year')->orderBy('terms.order'))
            ->description('Professores lançam as notas pelo diário. Aqui a secretaria lança o que faltou e corrige notas, inclusive de bimestre fechado; cada alteração pede o motivo e fica registrada.')
            ->columns([
                TextColumn::make('term.name')
                    ->label('Bimestre')
                    ->icon(fn (Grade $record): ?Heroicon => $record->term?->isClosed() ? Heroicon::OutlinedLockClosed : null)
                    ->tooltip(fn (Grade $record): ?string => $record->term?->isClosed() ? 'Bimestre fechado' : null),
                TextColumn::make('classroomSubject.subject.name')
                    ->label('Disciplina')
                    ->weight('medium'),
                TextColumn::make('grade_1')->label('N1')->numeric(2, ',', '.')->placeholder('—')->alignCenter()->visible($numeric),
                TextColumn::make('grade_2')->label('N2')->numeric(2, ',', '.')->placeholder('—')->alignCenter()->visible($numeric),
                TextColumn::make('recovery_grade')->label('Recuperação')->numeric(2, ',', '.')->placeholder('—')->alignCenter()->visible($numeric),
                TextColumn::make('result')
                    ->label($numeric ? 'Média' : 'Conceito')
                    ->state(fn (Grade $record): ?string => $record->grade !== null
                        ? number_format((float) $record->grade, 2, ',', '.')
                        : $record->conceptScale?->code)
                    ->badge()
                    ->placeholder('—')
                    ->alignCenter(),
                TextColumn::make('teacher.name')->label('Docente')->placeholder('Secretaria'),
                TextColumn::make('changes_count')
                    ->label('Correções')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? (string) $state : '—')
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('term_id')
                    ->label('Bimestre')
                    ->options(fn (): array => $this->terms()->pluck('name', 'id')->all()),
            ])
            ->headerActions([
                $this->correctAction('launch')
                    ->label('Lançar ou corrigir nota')
                    ->icon(Heroicon::OutlinedPlus),
            ])
            ->recordActions([
                $this->correctAction('correct')
                    ->label('Corrigir')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->fillForm(fn (Grade $record): array => [
                        'classroom_subject_id' => $record->classroom_subject_id,
                        'term_id' => $record->term_id,
                        'grade_1' => $record->grade_1,
                        'grade_2' => $record->grade_2,
                        'recovery_grade' => $record->recovery_grade,
                        'concept_scale_id' => $record->concept_scale_id,
                        'comments' => $record->comments,
                    ]),
                Action::make('history')
                    ->label('Correções')
                    ->icon(Heroicon::OutlinedClock)
                    ->color('gray')
                    ->visible(fn (Grade $record): bool => $record->changes_count > 0)
                    ->modalHeading('Correções desta nota')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->schema(fn (Grade $record): array => [
                        RepeatableEntry::make('changes')
                            ->hiddenLabel()
                            ->state($record->changes()->with('user')->latest('id')->get()->map(fn (GradeChange $change): array => [
                                'when' => $change->created_at?->format('d/m/Y H:i'),
                                'who' => $change->user?->name ?? '—',
                                'what' => self::describeChange($change->before, $change->after),
                                'reason' => $change->reason,
                            ])->all())
                            ->table([
                                TableColumn::make('Quando'),
                                TableColumn::make('Quem'),
                                TableColumn::make('Alteração'),
                                TableColumn::make('Motivo'),
                            ])
                            ->schema([
                                TextEntry::make('when'),
                                TextEntry::make('who'),
                                TextEntry::make('what'),
                                TextEntry::make('reason'),
                            ]),
                    ]),
            ])
            ->emptyStateHeading('Nenhuma nota lançada')
            ->emptyStateDescription('As notas aparecem aqui conforme os professores lançam no diário.')
            ->paginated(false);
    }

    private function correctAction(string $name): Action
    {
        $numeric = $this->mode() === AssessmentMode::Numeric;

        return Action::make($name)
            ->modalHeading('Lançar ou corrigir nota')
            ->modalDescription('Se já houver nota da disciplina no bimestre, ela é substituída. O motivo fica registrado.')
            ->modalSubmitActionLabel('Salvar')
            ->schema([
                Grid::make(2)->schema([
                    Select::make('classroom_subject_id')
                        ->label('Disciplina')
                        ->options(fn (): array => $this->owner()->classroom->classroomSubjects()->with('subject')->get()
                            ->mapWithKeys(fn (ClassroomSubject $assignment): array => [$assignment->id => $assignment->subject->name])
                            ->sort()->all())
                        ->required()
                        ->native(false),
                    Select::make('term_id')
                        ->label('Bimestre')
                        ->options(fn (): array => $this->terms()->get()
                            ->mapWithKeys(fn (Term $term): array => [$term->id => $term->name.($term->isClosed() ? ' · fechado' : '')])
                            ->all())
                        ->required()
                        ->native(false),
                ]),
                Grid::make(3)->visible($numeric)->schema([
                    TextInput::make('grade_1')->label('N1')->numeric()->minValue(0)->maxValue(10),
                    TextInput::make('grade_2')->label('N2')->numeric()->minValue(0)->maxValue(10),
                    TextInput::make('recovery_grade')->label('Recuperação')->numeric()->minValue(0)->maxValue(10),
                ]),
                Select::make('concept_scale_id')
                    ->label('Conceito')
                    ->options(fn (): array => ConceptScale::query()
                        ->when(auth()->user()?->company_id, fn (Builder $query, int $companyId): Builder => $query->where('company_id', $companyId))
                        ->orderBy('sort_order')->get()
                        ->mapWithKeys(fn (ConceptScale $concept): array => [$concept->id => $concept->code.' — '.$concept->label])->all())
                    ->native(false)
                    ->visible(! $numeric),
                Textarea::make('comments')->label('Observação do bimestre')->rows(2)->maxLength(2000),
                TextInput::make('reason')
                    ->label('Motivo')
                    ->placeholder('Ex.: nota da prova de recuperação entregue à secretaria')
                    ->required()
                    ->maxLength(500),
            ])
            ->action(function (array $data): void {
                $enrollment = $this->owner();

                app(GradeCorrection::class)->save(
                    $enrollment,
                    ClassroomSubject::query()->findOrFail($data['classroom_subject_id']),
                    Term::query()->findOrFail($data['term_id']),
                    $data,
                    auth()->user(),
                    $data['reason'],
                );

                Notification::make()->success()->title('Nota salva')->send();
            });
    }

    /** "N1: 5,00 → 7,00; Média: 5,50 → 6,50" */
    public static function describeChange(?array $before, array $after): string
    {
        $labels = ['grade_1' => 'N1', 'grade_2' => 'N2', 'recovery_grade' => 'Recuperação', 'grade' => 'Média', 'concept_scale_id' => 'Conceito', 'comments' => 'Observação'];
        $format = function (string $field, mixed $value): string {
            if ($value === null || $value === '') {
                return '—';
            }

            return match ($field) {
                'concept_scale_id' => ConceptScale::query()->whereKey($value)->value('code') ?? (string) $value,
                'comments' => '"'.str((string) $value)->limit(40).'"',
                default => number_format((float) $value, 2, ',', '.'),
            };
        };

        return collect($labels)
            ->filter(fn (string $label, string $field): bool => ($before[$field] ?? null) !== ($after[$field] ?? null))
            ->map(fn (string $label, string $field): string => $before === null
                ? "{$label}: ".$format($field, $after[$field] ?? null)
                : "{$label}: ".$format($field, $before[$field] ?? null).' → '.$format($field, $after[$field] ?? null))
            ->implode('; ') ?: 'Sem alteração de valores';
    }

    private function owner(): Enrollment
    {
        /** @var Enrollment $enrollment */
        $enrollment = $this->getOwnerRecord();

        return $enrollment->loadMissing(['classroom', 'academicYear']);
    }

    private function mode(): AssessmentMode
    {
        return $this->owner()->classroom?->assessment_mode ?? AssessmentMode::Numeric;
    }

    /** Bimestres do ano letivo da matrícula. */
    private function terms(): Builder
    {
        return Term::query()
            ->when($this->owner()->academicYear?->year, fn (Builder $query, int $year): Builder => $query->where('year', $year))
            ->orderBy('year')->orderBy('order');
    }
}
