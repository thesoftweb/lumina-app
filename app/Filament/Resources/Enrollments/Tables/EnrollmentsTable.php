<?php

namespace App\Filament\Resources\Enrollments\Tables;

use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Models\Enrollment;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EnrollmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Enrollment $record): string => EnrollmentResource::getUrl('edit', ['record' => $record]))
            ->modifyQueryUsing(fn ($query) => $query->with(['student.customer', 'classroom']))
            ->columns([
                TextColumn::make('student.name')
                    ->searchable()
                    ->label('Aluno'),
                TextColumn::make('student.customer.name')
                    ->label('Responsável')
                    ->searchable(),
                TextColumn::make('classroom.name')
                    ->label('Turma'),
                TextColumn::make('enrollment_date')
                    ->label('Data da Matrícula')
                    ->date('d/m/Y'),
                TextColumn::make('status')
                    ->label('Situação')
                    ->badge(),
            ])
            ->filters([
                //
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
