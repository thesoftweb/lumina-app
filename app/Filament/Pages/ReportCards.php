<?php

namespace App\Filament\Pages;

use App\Enums\AssessmentMode;
use App\Enums\NavGroup;
use App\Enums\TranscriptResult;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Services\Academic\AnnualResultCalculator;
use App\Services\Academic\YearClosing;
use App\Services\ReportCardService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class ReportCards extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::Academic;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Boletins';

    protected static ?string $title = 'Gerar boletins';

    protected ?string $subheading = 'Consulte, visualize e gere o PDF dos boletins dos alunos.';

    protected string $view = 'filament.pages.report-cards';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user?->hasAnyRole(['secretary', 'admin'])
            || ($user?->hasRole('teacher') && $user->can('view_report_cards') && (bool) $user->teacher);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->enrollmentsQuery())
            ->columns([
                TextColumn::make('student.name')
                    ->label('Aluno')
                    ->description(fn (Enrollment $record): ?string => $record->student->reg_number
                        ? 'Matrícula: '.$record->student->reg_number
                        : null)
                    ->icon(Heroicon::OutlinedUser)
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('classroom.name')
                    ->label('Turma')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('academicYear.description')
                    ->label('Ano letivo')
                    ->placeholder('Não informado')
                    ->sortable(),
                TextColumn::make('classroom.assessment_mode')
                    ->label('Avaliação')
                    ->formatStateUsing(fn (AssessmentMode|string|null $state): string => match (true) {
                        $state instanceof AssessmentMode => $state === AssessmentMode::Numeric ? 'Notas' : 'Conceitos',
                        $state === AssessmentMode::Concept->value => 'Conceitos',
                        default => 'Notas',
                    })
                    ->badge()
                    ->color(fn (AssessmentMode|string|null $state): string => ($state instanceof AssessmentMode ? $state : AssessmentMode::tryFrom($state ?? '')) === AssessmentMode::Concept ? 'info' : 'success'),
                TextColumn::make('grade_count')
                    ->label('Lançamentos')
                    ->counts('grade')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray')
                    ->alignCenter(),
                TextColumn::make('annual_result')
                    ->label('Situação')
                    ->state(fn (Enrollment $record): TranscriptResult => $record->transcriptYear?->result
                        ?? app(AnnualResultCalculator::class)->forEnrollment($record)->result)
                    ->badge()
                    ->description(fn (Enrollment $record): ?string => $record->transcriptYear ? 'Ano fechado' : null),
            ])
            ->filters([
                SelectFilter::make('classroom_id')
                    ->label('Turma')
                    ->options(fn (): array => $this->classroomsQuery()->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('academic_year_id')
                    ->label('Ano letivo')
                    ->relationship('academicYear', 'description')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Prévia')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->slideOver()
                    ->modalHeading(fn (Enrollment $record): string => 'Boletim — '.$record->student->name)
                    ->fillForm(fn (Enrollment $record): array => $this->reportCardState($record))
                    ->schema([
                        Section::make('Identificação')
                            ->icon(Heroicon::OutlinedUser)
                            ->columns(2)
                            ->schema([
                                TextEntry::make('student_name')
                                    ->label('Aluno')
                                    ->weight('bold'),
                                TextEntry::make('classroom_name')
                                    ->label('Turma')
                                    ->badge()
                                    ->color('info'),
                                TextEntry::make('academic_year')
                                    ->label('Ano letivo')
                                    ->placeholder('Não informado'),
                                TextEntry::make('assessment_mode')
                                    ->label('Tipo de avaliação')
                                    ->badge()
                                    ->color(fn (string $state): string => $state === 'Conceitos' ? 'info' : 'success'),
                            ]),
                        Section::make('Desempenho acadêmico')
                            ->icon(Heroicon::OutlinedAcademicCap)
                            ->schema([
                                RepeatableEntry::make('subjects')
                                    ->label('Disciplinas')
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label('Disciplina')
                                            ->weight('bold')
                                            ->icon(Heroicon::OutlinedBookOpen),
                                        TextEntry::make('results')
                                            ->label('Resultados por período')
                                            ->badge()
                                            ->color('primary'),
                                        TextEntry::make('annual_average')
                                            ->label($this->conceptLabel())
                                            ->badge()
                                            ->color('gray'),
                                        TextEntry::make('status')
                                            ->label('Situação')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'Aprovado' => 'success',
                                                'Reprovado' => 'danger',
                                                default => 'gray',
                                            }),
                                    ])
                                    ->columns(2),
                            ]),
                        Section::make('Frequência')
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->columns(3)
                            ->schema([
                                TextEntry::make('absences')
                                    ->label('Faltas')
                                    ->badge()
                                    ->color('danger'),
                                TextEntry::make('justified_absences')
                                    ->label('Justificadas')
                                    ->badge()
                                    ->color('warning'),
                                TextEntry::make('attendance_percentage')
                                    ->label('Frequência')
                                    ->badge()
                                    ->color('success'),
                            ]),
                        Section::make('Situação no ano')
                            ->icon(Heroicon::OutlinedCheckBadge)
                            ->columns(2)
                            ->schema([
                                TextEntry::make('result')
                                    ->label('Resultado')
                                    ->badge()
                                    ->color(fn (string $state): string => TranscriptResult::tryFromLabel($state)?->getColor() ?? 'gray'),
                                TextEntry::make('rules')
                                    ->label('Critério'),
                            ]),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),
                Action::make('view')
                    ->label('Visualizar PDF')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('gray')
                    ->url(fn (Enrollment $record): string => route('report-cards.show', $record))
                    ->openUrlInNewTab(),
                Action::make('pdf')
                    ->label('Baixar PDF')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('success')
                    ->url(fn (Enrollment $record): string => route('report-cards.pdf', $record))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('student.name')
            ->searchPlaceholder('Buscar por aluno ou turma...')
            ->emptyStateHeading('Nenhuma matrícula ativa encontrada')
            ->emptyStateDescription('Verifique os filtros ou cadastre uma matrícula ativa para gerar o boletim.')
            ->emptyStateIcon(Heroicon::OutlinedDocumentText)
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->striped();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('closeYear')
                ->label('Fechar ano letivo')
                ->icon(Heroicon::OutlinedLockClosed)
                ->visible(fn (): bool => (bool) Auth::user()?->hasAnyRole(['secretary', 'admin']))
                ->modalHeading('Fechar ano letivo da turma')
                ->modalDescription('Grava o resultado de cada aluno no histórico escolar: médias ou conceitos finais, carga horária, frequência e resultado. Fechar de novo atualiza os dados; resultados ajustados manualmente são mantidos.')
                ->modalSubmitActionLabel('Fechar ano')
                ->schema([
                    Select::make('classroom_id')
                        ->label('Turma')
                        ->options(fn (): array => Classroom::query()->pluck('name', 'id')->all())
                        ->required()
                        ->live()
                        ->native(false),
                    Select::make('academic_year_id')
                        ->label('Ano letivo')
                        ->options(fn (): array => AcademicYear::query()->orderByDesc('year')->pluck('description', 'id')->all())
                        ->default(fn (): ?int => AcademicYear::query()->where('year', now()->year)->value('id'))
                        ->required()
                        ->live()
                        ->native(false),
                    TextEntry::make('preview')
                        ->label('Prévia')
                        ->state(fn (Get $get): ?string => $this->closingPreview($get('classroom_id'), $get('academic_year_id')))
                        ->visible(fn (Get $get): bool => filled($get('classroom_id')) && filled($get('academic_year_id'))),
                ])
                ->action(function (array $data, Action $action): void {
                    try {
                        $years = app(YearClosing::class)->closeClassroom(
                            Classroom::query()->findOrFail($data['classroom_id']),
                            AcademicYear::query()->findOrFail($data['academic_year_id']),
                            Auth::user(),
                        );
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title('Ano não fechado')->body(collect($exception->errors())->flatten()->implode(' '))->persistent()->send();
                        $action->halt();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title('Ano letivo fechado')
                        ->body($years->count().' aluno(s): '.$years->countBy(fn ($year): string => $year->result->getLabel())
                            ->map(fn (int $count, string $label): string => "{$count} {$label}")->implode(', ').'.')
                        ->send();
                }),
        ];
    }

    private function closingPreview(mixed $classroomId, mixed $academicYearId): ?string
    {
        $classroom = Classroom::query()->find($classroomId);
        $academicYear = AcademicYear::query()->find($academicYearId);

        if (! $classroom || ! $academicYear) {
            return null;
        }

        $preview = app(YearClosing::class)->preview($classroom, $academicYear, Auth::user());

        if ($preview['total'] === 0) {
            return 'Nenhuma matrícula ativa nesta turma e ano letivo.';
        }

        $summary = $preview['total'].' aluno(s): '.collect($preview['results'])->map(fn (int $count, string $label): string => "{$count} {$label}")->implode(', ').'.';

        return $preview['pending']->isEmpty()
            ? $summary
            : $summary.' Faltam notas de '.$preview['pending']->count().' aluno(s), por exemplo '.$preview['pending']->first()['student'].' ('.$preview['pending']->first()['subjects'].'). Lance as notas antes de fechar.';
    }

    private function conceptLabel(): string
    {
        return 'Média ou conceito final';
    }

    /** @return array<string, mixed> */
    private function reportCardState(Enrollment $enrollment): array
    {
        $report = app(ReportCardService::class)->forEnrollment($enrollment);
        $isNumeric = $report['assessmentMode'] === AssessmentMode::Numeric;

        return [
            'result' => $report['annual']->result->getLabel(),
            'rules' => $report['annual']->rulesLabel(),
            'student_name' => $report['student']->name,
            'classroom_name' => $report['classroom']->name,
            'academic_year' => $report['academicYear']?->description,
            'assessment_mode' => $isNumeric ? 'Notas' : 'Conceitos',
            'subjects' => $report['subjects']->map(fn (array $subject): array => [
                'name' => $subject['name'],
                'results' => $report['terms']->map(fn ($term): string => $term->name.': '.($subject['grades'][$term->id] ?? '—'))->all(),
                'annual_average' => $subject['final'] === '—' ? 'Pendente' : $subject['final'],
                'status' => $subject['status_label'],
            ])->all(),
            'absences' => $report['attendance']['absences'],
            'justified_absences' => $report['attendance']['justified'],
            'attendance_percentage' => $report['attendance']['percentage'] !== null
                ? $report['attendance']['percentage'].'%'
                : 'Sem registros',
        ];
    }

    private function enrollmentsQuery(): Builder
    {
        return Enrollment::query()
            ->with(['student', 'classroom.level', 'classroom.classroomSubjects.subject', 'academicYear', 'transcriptYear'])
            ->where('status', 'active')
            ->when(Auth::user()->hasRole('teacher'), function (Builder $query): void {
                $teacherId = Auth::user()->teacher?->id ?? 0;
                $query->whereHas('classroom.teachers', fn (Builder $query) => $query->whereKey($teacherId));
            });
    }

    private function classroomsQuery(): Builder
    {
        return Classroom::query()
            ->when(Auth::user()->hasRole('teacher'), function (Builder $query): void {
                $teacherId = Auth::user()->teacher?->id ?? 0;
                $query->whereHas('teachers', fn (Builder $query) => $query->whereKey($teacherId));
            });
    }
}
