<?php

namespace App\Filament\Resources\Enrollments\Tables;

use App\Enums\EnrollmentStatus;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EnrollmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Enrollment $record): string => EnrollmentResource::getUrl('view', ['record' => $record]))
            ->modifyQueryUsing(fn ($query) => $query->with(['student.customer', 'classroom', 'academicYear']))
            ->searchPlaceholder('Buscar aluno ou responsável')
            ->emptyStateHeading('Nenhuma matrícula encontrada')
            ->emptyStateDescription('Cadastre uma matrícula ou ajuste os filtros da busca.')
            ->columns([
                TextColumn::make('student.name')
                    ->searchable()
                    ->label('Aluno'),
                TextColumn::make('student.customer.name')
                    ->label('Responsável')
                    ->searchable(),
                TextColumn::make('classroom.name')
                    ->label('Turma'),
                TextColumn::make('academicYear.description')
                    ->label('Ano letivo')
                    ->placeholder('Não informado'),
                TextColumn::make('enrollment_date')
                    ->label('Data da Matrícula')
                    ->date('d/m/Y'),
                TextColumn::make('status')
                    ->label('Situação')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('academic_year_id')
                    ->label('Ano letivo')
                    ->options(fn (): array => AcademicYear::query()->orderByDesc('year')->pluck('description', 'id')->all())
                    ->searchable(),
                SelectFilter::make('classroom_id')
                    ->label('Turma')
                    ->options(fn (): array => Classroom::query()->pluck('name', 'id')->all())
                    ->searchable(),
                SelectFilter::make('status')
                    ->label('Situação')
                    ->options(EnrollmentStatus::class),
                Filter::make('enrollment_date')
                    ->label('Período da matrícula')
                    ->schema([
                        DatePicker::make('from')->label('De'),
                        DatePicker::make('until')->label('Até')->afterOrEqual('from'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('enrollment_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('enrollment_date', '<=', $date))),
            ])
            ->defaultSort('enrollment_date', 'desc')
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
