<?php

namespace App\Filament\Pages;

use App\Enums\NavGroup;
use App\Enums\AssessmentMode;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Services\ReportCardService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
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

        return $user?->hasAnyRole(['coordinator', 'admin'])
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
                                            ->label('Média anual')
                                            ->badge()
                                            ->color(fn (string $state): string => $state === 'Não se aplica' ? 'gray' : 'success'),
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
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),
                Action::make('view')
                    ->label('Abrir')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Enrollment $record): string => route('report-cards.show', $record))
                    ->openUrlInNewTab(),
                Action::make('pdf')
                    ->label('Gerar PDF')
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

    /** @return array<string, mixed> */
    private function reportCardState(Enrollment $enrollment): array
    {
        $report = app(ReportCardService::class)->forEnrollment($enrollment);
        $isNumeric = $report['assessmentMode'] === AssessmentMode::Numeric;

        return [
            'student_name' => $report['student']->name,
            'classroom_name' => $report['classroom']->name,
            'academic_year' => $report['academicYear']?->description,
            'assessment_mode' => $isNumeric ? 'Notas' : 'Conceitos',
            'subjects' => $report['subjects']->map(fn (array $subject): array => [
                'name' => $subject['name'],
                'results' => $report['terms']->map(fn ($term): string => $term->name.': '.($subject['grades'][$term->id] ?? '—'))->all(),
                'annual_average' => $isNumeric
                    ? ($subject['annual_average'] !== null ? number_format($subject['annual_average'], 2, ',', '.') : 'Pendente')
                    : 'Não se aplica',
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
            ->with(['student', 'classroom.level', 'academicYear'])
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
