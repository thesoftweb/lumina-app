<?php

namespace App\Filament\Resources\Students\Tables;

use App\Enums\EnrollmentStatus;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Classroom;
use App\Models\Student;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Student $record): string => StudentResource::getUrl(
                Auth::user()->can('update', $record) ? 'edit' : 'view',
                ['record' => $record],
            ))
            ->defaultSort('name')
            ->searchPlaceholder('Buscar aluno, CPF ou filiação')
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Nenhum aluno encontrado')
            ->emptyStateDescription('Cadastre um aluno ou ajuste os filtros da busca.')
            ->columns([
                TextColumn::make('name')
                    ->label('Aluno')
                    ->weight('medium')
                    ->description(fn (Student $record): string => $record->customer?->name
                        ? 'Responsável financeiro: '.$record->customer->name
                        : 'Responsável financeiro não informado')
                    ->searchable(['name', 'doc_number', 'affiliation_1', 'affiliation_2'])
                    ->sortable(),
                TextColumn::make('date_of_birth')->label('Nascimento')->date('d/m/Y')->sortable(),
                TextColumn::make('classroom_name')
                    ->label('Última turma')
                    ->state(fn (Student $record): ?string => $record->latestEnrollment?->classroom?->name)
                    ->placeholder('Sem matrícula'),
                TextColumn::make('enrollment_status')
                    ->label('Matrícula')
                    ->state(fn (Student $record): ?string => $record->latestEnrollment?->status?->value)
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? EnrollmentStatus::from($state)->getLabel()
                        : 'Sem matrícula')
                    ->color(fn (?string $state): string => $state
                        ? EnrollmentStatus::from($state)->getColor()
                        : 'gray'),
                TextColumn::make('phone_primary')->label('Contato')->icon('heroicon-o-phone')
                    ->placeholder('Não informado')->searchable()->toggleable(),
                TextColumn::make('doc_number')->label('CPF')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('id')->label('ID')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('classroom_id')->label('Turma')
                    ->options(fn (): array => Classroom::query()->pluck('name', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $id): Builder => $query->whereHas('latestEnrollment', fn (Builder $enrollment): Builder => $enrollment->where('classroom_id', $id)),
                    )),
                SelectFilter::make('enrollment_status')->label('Situação da matrícula')
                    ->options(collect(EnrollmentStatus::cases())->mapWithKeys(fn (EnrollmentStatus $status): array => [$status->value => $status->getLabel()])->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $status): Builder => $query->whereHas('latestEnrollment', fn (Builder $enrollment): Builder => $enrollment->where('status', $status)),
                    )),
                TernaryFilter::make('has_enrollments')->label('Matrículas')
                    ->placeholder('Todos os alunos')->trueLabel('Com matrícula')->falseLabel('Sem matrícula')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->has('enrollments'),
                        false: fn (Builder $query): Builder => $query->doesntHave('enrollments'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                SelectFilter::make('birth_month')->label('Mês de aniversário')
                    ->options(array_combine(range(1, 12), ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro']))
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $month): Builder => $query->whereMonth('date_of_birth', $month),
                    )),
            ])
            ->recordActions([
                EditAction::make()->label('Editar')->color('gray'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
