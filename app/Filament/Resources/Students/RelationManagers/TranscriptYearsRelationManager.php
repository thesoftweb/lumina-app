<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Documents\SchoolTranscript;
use App\Enums\AssessmentMode;
use App\Enums\TranscriptResult;
use App\Filament\Actions\IssueDocumentAction;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\TranscriptYear;
use App\Services\Academic\AnnualResultCalculator;
use App\Services\Academic\YearClosing;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

/**
 * Aba "Histórico escolar" do aluno: os anos cursados nesta escola (gravados ao fechar o ano letivo)
 * e em outras (lançados a partir da documentação de transferência), e a emissão do histórico.
 */
class TranscriptYearsRelationManager extends RelationManager
{
    protected static string $relationship = 'transcriptYears';

    protected static ?string $title = 'Histórico escolar';

    protected static ?string $modelLabel = 'ano do histórico';

    protected static ?string $pluralModelLabel = 'anos do histórico';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Ano cursado')
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextInput::make('year')->label('Ano')->numeric()->minValue(1950)->maxValue((int) now()->year)->required(),
                    TextInput::make('grade_label')->label('Série / ano escolar')->placeholder('2º ano')->required()->maxLength(60),
                    Select::make('assessment_mode')
                        ->label('Avaliação')
                        ->options([AssessmentMode::Numeric->value => 'Notas', AssessmentMode::Concept->value => 'Conceitos'])
                        ->default(AssessmentMode::Numeric->value)
                        ->required()
                        ->live()
                        ->native(false),
                    TextInput::make('school_name')->label('Escola')->required()->maxLength(255)->columnSpan(['md' => 2]),
                    Grid::make(2)->columnSpan(1)->schema([
                        TextInput::make('school_city')->label('Cidade')->maxLength(255),
                        TextInput::make('school_state')->label('UF')->maxLength(2)->extraInputAttributes(['style' => 'text-transform: uppercase']),
                    ]),
                    Select::make('result')
                        ->label('Resultado')
                        ->options(collect([TranscriptResult::Approved, TranscriptResult::Failed, TranscriptResult::FailedAttendance, TranscriptResult::Transferred])
                            ->mapWithKeys(fn (TranscriptResult $result): array => [$result->value => $result->getLabel()]))
                        ->required()
                        ->native(false),
                    TextInput::make('attendance_percent')->label('Frequência')->numeric()->minValue(0)->maxValue(100)->suffix('%'),
                    TextInput::make('school_days')->label('Dias letivos')->numeric()->minValue(0)->maxValue(366),
                    TextInput::make('total_workload')
                        ->label('Carga horária total')
                        ->numeric()->minValue(0)->suffix('h')
                        ->helperText('Em branco, soma as cargas das disciplinas.'),
                ]),
            Repeater::make('subjects')
                ->label('Componentes curriculares')
                ->relationship()
                ->orderColumn('sort_order')
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextInput::make('name')->label('Componente')->required()->maxLength(255),
                    TextInput::make('final_grade')
                        ->label('Nota final')
                        ->numeric()->minValue(0)->maxValue(10)
                        ->visible(fn (Get $get): bool => $get('../../assessment_mode') !== AssessmentMode::Concept->value),
                    TextInput::make('concept')
                        ->label('Conceito')
                        ->maxLength(20)
                        ->visible(fn (Get $get): bool => $get('../../assessment_mode') === AssessmentMode::Concept->value),
                    TextInput::make('workload_hours')->label('Carga horária')->numeric()->minValue(0)->suffix('h'),
                ])
                ->defaultItems(0)
                ->addActionLabel('Adicionar componente')
                ->reorderable(),
            Textarea::make('notes')->label('Observação do ano')->rows(2)->maxLength(1000),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('subjects'))
            ->description('Anos desta escola entram ao fechar o ano letivo (Boletins › Fechar ano letivo) ou pelo botão "Registrar ano desta escola". Anos de outras escolas são lançados a partir da documentação de transferência.')
            ->columns([
                TextColumn::make('year')->label('Ano')->weight('medium'),
                TextColumn::make('grade_label')->label('Série'),
                TextColumn::make('school_name')
                    ->label('Escola')
                    ->description(fn (TranscriptYear $record): ?string => $record->schoolPlace())
                    ->wrap(),
                TextColumn::make('result')
                    ->label('Resultado')
                    ->badge()
                    ->description(fn (TranscriptYear $record): ?string => $record->isAdjusted() ? 'Ajustado: '.$record->adjustment_reason : null),
                TextColumn::make('attendance_percent')->label('Frequência')->suffix('%')->numeric(1, ',', '.')->placeholder('—'),
                TextColumn::make('subjects_count')->label('Componentes')->alignCenter(),
                TextColumn::make('source')
                    ->label('Origem')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === TranscriptYear::INTERNAL ? 'Esta escola' : 'Outra escola')
                    ->color(fn (string $state): string => $state === TranscriptYear::INTERNAL ? 'info' : 'gray'),
            ])
            ->headerActions([
                $this->issueAction(),
                $this->recordEnrollmentAction(),
                CreateAction::make()
                    ->label('Adicionar ano de outra escola')
                    ->icon(Heroicon::OutlinedPlus)
                    ->color('gray')
                    ->modalHeading('Ano cursado em outra escola')
                    ->modalDescription('Lance os dados como estão no histórico ou na declaração de transferência recebida.')
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'source' => TranscriptYear::EXTERNAL,
                        'school_state' => filled($data['school_state'] ?? null) ? mb_strtoupper($data['school_state']) : null,
                        'recorded_by' => auth()->id(),
                    ])
                    ->after(fn (TranscriptYear $record) => $this->fillWorkload($record)),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (TranscriptYear $record): bool => ! $record->isInternal())
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'school_state' => filled($data['school_state'] ?? null) ? mb_strtoupper($data['school_state']) : null,
                    ])
                    ->after(fn (TranscriptYear $record) => $this->fillWorkload($record)),
                $this->adjustAction(),
                DeleteAction::make()
                    ->modalDescription(fn (TranscriptYear $record): string => $record->isInternal()
                        ? 'O ano sai do histórico; fechar o ano letivo de novo grava ele outra vez. Documentos já emitidos não mudam.'
                        : 'O ano sai do histórico. Documentos já emitidos não mudam.'),
            ])
            ->defaultSort('year')
            ->paginated(false)
            ->emptyStateHeading('Nenhum ano no histórico')
            ->emptyStateDescription('Lance os anos de outras escolas e feche o ano letivo desta escola para montar o histórico.');
    }

    private function issueAction(): Action
    {
        return IssueDocumentAction::make(
            name: 'issueTranscript',
            label: 'Emitir histórico',
            issue: fn (array $data): \App\Models\IssuedDocument => SchoolTranscript::issue(
                $this->student(),
                auth()->user(),
                (bool) ($data['include_current_year'] ?? false),
                $data['observations'] ?? null,
            ),
            fields: [
                Toggle::make('include_current_year')
                    ->label('Incluir o ano em curso como "Cursando"')
                    ->helperText(fn (): string => ($enrollment = SchoolTranscript::currentEnrollment($this->student()))
                        ? 'Ano '.$enrollment->academicYear->year.' ('.$enrollment->classroom->name.'), com as médias parciais. Use para transferência no meio do ano.'
                        : 'Não há ano em curso fora do histórico.')
                    ->default(fn (): bool => SchoolTranscript::currentEnrollment($this->student()) !== null)
                    ->disabled(fn (): bool => SchoolTranscript::currentEnrollment($this->student()) === null),
                Textarea::make('observations')
                    ->label('Observações')
                    ->placeholder('Ex.: Aluno(a) transferido(a) em 10/06/2026, apto(a) a cursar o 4º ano.')
                    ->rows(3)
                    ->maxLength(1500),
            ],
            withPurposeAndValidity: false,
        )->icon(Heroicon::OutlinedDocumentText)->color('primary');
    }

    private function recordEnrollmentAction(): Action
    {
        return Action::make('recordEnrollment')
            ->label('Registrar ano desta escola')
            ->icon(Heroicon::OutlinedAcademicCap)
            ->color('gray')
            ->modalHeading('Registrar ano desta escola')
            ->modalDescription('Grava no histórico as médias ou conceitos, a carga horária e a frequência da matrícula. Use para um aluno transferido no meio do ano ou para fechar um aluno só.')
            ->modalSubmitActionLabel('Registrar')
            ->schema([
                Select::make('enrollment_id')
                    ->label('Matrícula')
                    ->options(fn (): array => $this->student()->enrollments()
                        ->with(['classroom', 'academicYear'])
                        ->whereDoesntHave('transcriptYear')
                        ->whereHas('academicYear')
                        ->get()
                        ->mapWithKeys(fn (Enrollment $enrollment): array => [$enrollment->id => $enrollment->academicYear->year.' · '.$enrollment->classroom->name.' ('.$enrollment->status->getLabel().')'])
                        ->all())
                    ->required()
                    ->live()
                    ->native(false),
                Select::make('result')
                    ->label('Resultado')
                    ->options(collect(TranscriptResult::cases())->reject(fn (TranscriptResult $result): bool => $result === TranscriptResult::InProgress)
                        ->mapWithKeys(fn (TranscriptResult $result): array => [$result->value => $result->getLabel()]))
                    ->placeholder('Usar o resultado calculado')
                    ->helperText(fn (Get $get): ?string => ($enrollment = Enrollment::find($get('enrollment_id')))
                        ? 'Calculado: '.app(AnnualResultCalculator::class)->forEnrollment($enrollment)->result->getLabel().'.'
                        : null)
                    ->live()
                    ->native(false),
                TextInput::make('reason')
                    ->label('Motivo do resultado diferente do calculado')
                    ->placeholder('Ex.: Transferido em 10/06/2026; conselho de classe de 18/12/2026')
                    ->maxLength(500)
                    ->visible(fn (Get $get): bool => filled($get('result'))),
            ])
            ->action(function (array $data, Action $action): void {
                $enrollment = $this->student()->enrollments()->findOrFail($data['enrollment_id']);

                try {
                    $year = app(YearClosing::class)->closeEnrollment(
                        $enrollment,
                        auth()->user(),
                        filled($data['result'] ?? null) ? TranscriptResult::from($data['result']) : null,
                        $data['reason'] ?? null,
                    );
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title('Ano não registrado')->body(collect($exception->errors())->flatten()->implode(' '))->persistent()->send();
                    $action->halt();

                    return;
                }

                Notification::make()->success()->title('Ano registrado no histórico')->body("{$year->year} · {$year->grade_label}: {$year->result->getLabel()}.")->send();
            });
    }

    /** Ano desta escola: só o resultado e a observação mudam aqui (as notas vêm da matrícula). */
    private function adjustAction(): Action
    {
        return Action::make('adjust')
            ->label('Ajustar')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->visible(fn (TranscriptYear $record): bool => $record->isInternal())
            ->modalHeading('Ajustar resultado')
            ->modalDescription('As notas vêm da matrícula: para corrigir uma nota, use a aba Notas da matrícula e feche o ano de novo. Aqui muda o resultado (ex.: conselho de classe) e a observação.')
            ->fillForm(fn (TranscriptYear $record): array => [
                'result' => $record->result->value,
                'reason' => $record->adjustment_reason,
                'notes' => $record->notes,
            ])
            ->schema([
                Select::make('result')
                    ->label('Resultado')
                    ->options(collect(TranscriptResult::cases())->reject(fn (TranscriptResult $result): bool => $result === TranscriptResult::InProgress)
                        ->mapWithKeys(fn (TranscriptResult $result): array => [$result->value => $result->getLabel()]))
                    ->helperText(fn (TranscriptYear $record): string => 'Calculado: '.($record->computed_result?->getLabel() ?? '—').'.')
                    ->required()
                    ->native(false),
                TextInput::make('reason')
                    ->label('Motivo (quando diferente do calculado)')
                    ->maxLength(500),
                Textarea::make('notes')->label('Observação do ano')->rows(2)->maxLength(1000),
            ])
            ->action(function (TranscriptYear $record, array $data, Action $action): void {
                $result = TranscriptResult::from($data['result']);

                if ($result !== $record->computed_result && blank($data['reason'] ?? null)) {
                    Notification::make()->danger()->title('Informe o motivo do resultado diferente do calculado')->send();
                    $action->halt();

                    return;
                }

                $record->update([
                    'result' => $result,
                    'adjustment_reason' => $result !== $record->computed_result ? trim($data['reason']) : null,
                    'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
                ]);

                Notification::make()->success()->title('Resultado ajustado')->send();
            });
    }

    /** Sem carga horária total informada, vale a soma das disciplinas. */
    private function fillWorkload(TranscriptYear $record): void
    {
        if ($record->total_workload === null && ($sum = (int) $record->subjects()->sum('workload_hours')) > 0) {
            $record->update(['total_workload' => $sum]);
        }
    }

    private function student(): Student
    {
        /** @var Student $student */
        $student = $this->getOwnerRecord();

        return $student;
    }
}
