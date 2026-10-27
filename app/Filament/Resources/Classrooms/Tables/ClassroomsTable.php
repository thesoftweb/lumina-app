<?php

namespace App\Filament\Resources\Classrooms\Tables;

use App\Enums\AssessmentMode;
use App\Models\AcademicYear;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ClassroomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Titulo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('academicYear.description')
                    ->label('Ano letivo')
                    ->placeholder('Sem ano')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('level.name')
                    ->label('Nivel')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('assessment_mode')
                    ->label('Avaliação')
                    ->formatStateUsing(fn (AssessmentMode|string $state): string => $state instanceof AssessmentMode ? $state->label() : (AssessmentMode::tryFrom($state)?->label() ?? $state))
                    ->badge()
                    ->color(fn (AssessmentMode|string $state): string => ($state instanceof AssessmentMode ? $state : AssessmentMode::tryFrom($state)) === AssessmentMode::Concept ? 'info' : 'success'),                TextColumn::make('enrollments_count')
                    ->label('Estudantes')
                    ->counts('enrollments')
                    ->badge()
                    ->sortable(),
                TextColumn::make('teachers_count')
                    ->label('Profesores')
                    ->counts('teachers')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('academic_year_id')
                    ->label('Ano letivo')
                    ->options(fn (): array => AcademicYear::query()->orderByDesc('year')->pluck('description', 'id')->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
