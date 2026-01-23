<?php

namespace App\Filament\Resources\Attendances\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AttendancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->label('Data'),

                TextColumn::make('classroom.name')
                    ->sortable()
                    ->searchable()
                    ->label('Turma'),

                TextColumn::make('enrollment.student.name')
                    ->sortable()
                    ->searchable()
                    ->label('Aluno'),

                BooleanColumn::make('present')
                    ->sortable()
                    ->label('Presente')
                    ->trueIcon('heroicon-m-check-circle')
                    ->falseIcon('heroicon-m-x-circle'),

                BooleanColumn::make('justified')
                    ->sortable()
                    ->label('Justificada'),

                TextColumn::make('teacher.name')
                    ->sortable()
                    ->searchable()
                    ->label('Registrado por'),
            ])
            ->filters([
                SelectFilter::make('classroom_id')
                    ->relationship('classroom', 'name')
                    ->label('Turma'),

                SelectFilter::make('present')
                    ->options([
                        true => 'Presentes',
                        false => 'Ausentes',
                    ])
                    ->label('Status'),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
