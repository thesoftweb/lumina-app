<?php

namespace App\Filament\Pages;

use App\Enums\AssessmentMode;
use App\Models\ConceptScale;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Term;
use App\Services\GradeCalculationService;
use App\Support\TeacherPanel;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class Gradebook extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    protected static ?string $navigationLabel = 'Lançar notas';

    protected static ?string $title = 'Lançamento de notas';

    protected string $view = 'filament.pages.gradebook';

    /** @var array{classroom_subject_id: int|null, term_id: int|null} */
    public array $data = ['classroom_subject_id' => null, 'term_id' => null];

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole('teacher')
            && Auth::user()?->can('create_grade')
            && (bool) Auth::user()?->teacher;
    }

    public function mount(): void
    {
        $this->form->fill([
            'classroom_subject_id' => TeacherPanel::classroomSubjects(Auth::user())
                ->orderBy('classroom_id')->orderBy('subject_id')->value('id'),
            'term_id' => Term::query()->where('year', now()->year)->orderBy('order')->value('id')
                ?? Term::query()->orderByDesc('year')->orderBy('order')->value('id'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Diário de avaliação')
                    ->description('As alterações são salvas automaticamente ao sair do campo.')
                    ->icon(Heroicon::OutlinedAcademicCap)
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('classroom_subject_id')
                                ->label('Turma / disciplina')
                                ->options(fn (): array => TeacherPanel::classroomSubjects(Auth::user())->get()
                                    ->mapWithKeys(fn ($assignment): array => [
                                        $assignment->id => $assignment->classroom->name.' — '.$assignment->subject->name,
                                    ])->all())
                                ->searchable()->preload()->native(false)->required()->live()
                                ->afterStateUpdated(fn () => $this->resetTable()),
                            Select::make('term_id')
                                ->label('Bimestre')
                                ->options(fn (): array => Term::query()->orderByDesc('year')->orderBy('order')->get()
                                    ->mapWithKeys(fn (Term $term): array => [
                                        $term->id => $term->name.($term->year ? " ({$term->year})" : ''),
                                    ])->all())
                                ->searchable()->preload()->native(false)->required()->live()
                                ->afterStateUpdated(fn () => $this->resetTable()),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        $assignmentId = $this->selectedAssignmentId();
        $termId = $this->selectedTermId();
        $assignment = $assignmentId ? TeacherPanel::ensureClassroomSubject(Auth::user(), $assignmentId) : null;
        $mode = $assignment?->classroom?->assessment_mode ?? AssessmentMode::Numeric;

        return $table
            ->query(
                Enrollment::query()
                    ->with([
                        'student',
                        'grade' => fn ($query) => $query
                            ->when($assignmentId, fn ($query) => $query->where('classroom_subject_id', $assignmentId))
                            ->when($termId, fn ($query) => $query->where('term_id', $termId))
                            ->with('conceptScale'),
                    ])
                    ->when(
                        $assignment && $termId,
                        fn ($query) => $query->where('classroom_id', $assignment->classroom_id)->where('status', 'active'),
                        fn ($query) => $query->whereRaw('1 = 0')
                    )
            )
            ->heading($assignment ? $assignment->classroom->name.' — '.$assignment->subject->name : 'Avaliações dos alunos')
            ->description($assignment
                ? ($mode === AssessmentMode::Numeric ? 'Modo numérico: N1, N2 e recuperação.' : 'Modo conceitual: conceito bimestral e observação.')
                : 'Selecione a turma/disciplina e o bimestre.')
            ->columns([
                TextColumn::make('student.name')
                    ->label('Aluno')->icon(Heroicon::OutlinedUser)->searchable()->sortable()->weight('medium'),
                TextInputColumn::make('grade_1')
                    ->label('N1')->type('number')->inputMode('decimal')->step(0.01)->placeholder('0,00')
                    ->rules(['nullable', 'numeric', 'min:0', 'max:10'])
                    ->getStateUsing(fn (Enrollment $record): mixed => $this->currentGrade($record)?->grade_1)
                    ->updateStateUsing(fn (Enrollment $record, mixed $state): mixed => $this->updateNumericGrade($record, 'grade_1', $state))
                    ->extraInputAttributes(['class' => 'text-center', 'min' => 0, 'max' => 10])
                    ->visible($mode === AssessmentMode::Numeric),
                TextInputColumn::make('grade_2')
                    ->label('N2')->type('number')->inputMode('decimal')->step(0.01)->placeholder('0,00')
                    ->rules(['nullable', 'numeric', 'min:0', 'max:10'])
                    ->getStateUsing(fn (Enrollment $record): mixed => $this->currentGrade($record)?->grade_2)
                    ->updateStateUsing(fn (Enrollment $record, mixed $state): mixed => $this->updateNumericGrade($record, 'grade_2', $state))
                    ->extraInputAttributes(['class' => 'text-center', 'min' => 0, 'max' => 10])
                    ->visible($mode === AssessmentMode::Numeric),
                TextInputColumn::make('recovery_grade')
                    ->label('Recuperação')->type('number')->inputMode('decimal')->step(0.01)->placeholder('—')
                    ->rules(['nullable', 'numeric', 'min:0', 'max:10'])
                    ->getStateUsing(fn (Enrollment $record): mixed => $this->currentGrade($record)?->recovery_grade)
                    ->updateStateUsing(fn (Enrollment $record, mixed $state): mixed => $this->updateNumericGrade($record, 'recovery_grade', $state))
                    ->extraInputAttributes(['class' => 'text-center', 'min' => 0, 'max' => 10])
                    ->visible($mode === AssessmentMode::Numeric),
                TextColumn::make('calculated_grade')
                    ->label('Média')->state(fn (Enrollment $record): mixed => $this->currentGrade($record)?->grade)
                    ->formatStateUsing(fn ($state): string => filled($state) ? number_format((float) $state, 2, ',', '.') : '—')
                    ->badge()->color(fn ($state): string => $this->numericColor($state))
                    ->visible($mode === AssessmentMode::Numeric),
                SelectColumn::make('concept_scale_id')
                    ->label('Conceito')->options(fn (Enrollment $record): array => $this->conceptOptions($this->currentGrade($record)?->concept_scale_id))
                    ->placeholder('Selecione')
                    ->getStateUsing(fn (Enrollment $record): mixed => $this->currentGrade($record)?->concept_scale_id)
                    ->updateStateUsing(fn (Enrollment $record, mixed $state): mixed => $this->updateConcept($record, $state))
                    ->visible($mode === AssessmentMode::Concept),
                TextInputColumn::make('comments')
                    ->label('Observações')->placeholder('Opcional...')->rules(['nullable', 'string', 'max:2000'])
                    ->getStateUsing(fn (Enrollment $record): mixed => $this->currentGrade($record)?->comments)
                    ->updateStateUsing(fn (Enrollment $record, mixed $state): mixed => $this->updateComments($record, $state))
                    ->extraInputAttributes(['maxlength' => 2000]),
                TextColumn::make('status')
                    ->label('Status')->state(fn (Enrollment $record): string => $this->isComplete($record, $mode) ? 'Completo' : 'Pendente')
                    ->badge()->color(fn (string $state): string => $state === 'Completo' ? 'success' : 'gray')
                    ->icon(fn (string $state): Heroicon => $state === 'Completo' ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedClock),
            ])
            ->defaultSort('student.name')->searchPlaceholder('Buscar aluno...')
            ->emptyStateHeading($assignment && $termId ? 'Nenhum aluno ativo nesta turma' : 'Selecione os dados do diário')
            ->emptyStateIcon(Heroicon::OutlinedAcademicCap)->paginated(false)->striped();
    }

    private function updateNumericGrade(Enrollment $enrollment, string $field, mixed $state): mixed
    {
        [$assignmentId, $termId, $mode] = $this->validatedContext($enrollment);
        abort_unless($mode === AssessmentMode::Numeric, 422);

        $grade = Grade::firstOrNew([
            'enrollment_id' => $enrollment->id,
            'classroom_subject_id' => $assignmentId,
            'term_id' => $termId,
        ]);
        $grade->teacher_id = TeacherPanel::teacher(Auth::user())->id;
        $grade->{$field} = filled($state) ? $state : null;
        $grade->concept_scale_id = null;
        $grade->grade = app(GradeCalculationService::class)->calculate(
            $grade->grade_1,
            $grade->grade_2,
            $grade->recovery_grade,
        );
        $grade->save();
        $this->reloadGrade($enrollment);

        return $state;
    }

    private function updateConcept(Enrollment $enrollment, mixed $state): mixed
    {
        [$assignmentId, $termId, $mode] = $this->validatedContext($enrollment);
        abort_unless($mode === AssessmentMode::Concept, 422);

        $concept = filled($state)
            ? ConceptScale::query()->whereKey($state)->where('company_id', Auth::user()->company_id)->where('is_active', true)->firstOrFail()
            : null;
        $grade = Grade::firstOrNew([
            'enrollment_id' => $enrollment->id,
            'classroom_subject_id' => $assignmentId,
            'term_id' => $termId,
        ]);
        $grade->fill([
            'teacher_id' => TeacherPanel::teacher(Auth::user())->id,
            'grade' => null,
            'grade_1' => null,
            'grade_2' => null,
            'recovery_grade' => null,
            'concept_scale_id' => $concept?->id,
        ])->save();
        $this->reloadGrade($enrollment);

        return $state;
    }

    private function updateComments(Enrollment $enrollment, mixed $state): mixed
    {
        [$assignmentId, $termId] = $this->validatedContext($enrollment);
        Grade::updateOrCreate(
            ['enrollment_id' => $enrollment->id, 'classroom_subject_id' => $assignmentId, 'term_id' => $termId],
            ['teacher_id' => TeacherPanel::teacher(Auth::user())->id, 'comments' => filled($state) ? $state : null]
        );
        $this->reloadGrade($enrollment);

        return $state;
    }

    /** @return array{int, int, AssessmentMode} */
    private function validatedContext(Enrollment $enrollment): array
    {
        $assignmentId = $this->selectedAssignmentId();
        $termId = $this->selectedTermId();
        abort_unless($assignmentId && $termId && Term::whereKey($termId)->exists(), 422);
        $assignment = TeacherPanel::ensureClassroomSubject(Auth::user(), $assignmentId);
        abort_unless($enrollment->classroom_id === $assignment->classroom_id && $enrollment->status->value === 'active', 403);

        return [$assignmentId, $termId, $assignment->classroom->assessment_mode ?? AssessmentMode::Numeric];
    }

    private function currentGrade(Enrollment $enrollment): ?Grade
    {
        return $enrollment->grade->first();
    }

    private function reloadGrade(Enrollment $enrollment): void
    {
        $assignmentId = $this->selectedAssignmentId();
        $termId = $this->selectedTermId();
        $enrollment->load(['grade' => fn ($query) => $query
            ->where('classroom_subject_id', $assignmentId)
            ->where('term_id', $termId)
            ->with('conceptScale')]);
    }

    private function isComplete(Enrollment $enrollment, AssessmentMode $mode): bool
    {
        $grade = $this->currentGrade($enrollment);

        return $mode === AssessmentMode::Numeric ? $grade?->grade !== null : $grade?->concept_scale_id !== null;
    }

    private function numericColor(mixed $grade): string
    {
        if (! is_numeric($grade)) {
            return 'gray';
        }

        $minimum = (float) (Auth::user()->company?->minimum_passing_grade ?? 6);

        return (float) $grade >= $minimum ? 'success' : 'danger';
    }

    private function conceptOptions(?int $includeId = null): array
    {
        return ConceptScale::query()
            ->where('company_id', Auth::user()->company_id)
            ->where(fn ($query) => $query->where('is_active', true)
                ->when($includeId, fn ($query) => $query->orWhere($query->qualifyColumn('id'), $includeId)))
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (ConceptScale $concept): array => [$concept->id => $concept->code.' — '.$concept->label])
            ->all();
    }

    private function selectedAssignmentId(): ?int
    {
        return filled($this->data['classroom_subject_id'] ?? null) ? (int) $this->data['classroom_subject_id'] : null;
    }

    private function selectedTermId(): ?int
    {
        return filled($this->data['term_id'] ?? null) ? (int) $this->data['term_id'] : null;
    }
}
