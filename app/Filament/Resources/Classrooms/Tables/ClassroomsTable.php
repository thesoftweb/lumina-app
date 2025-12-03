<?php

namespace App\Filament\Resources\Classrooms\Tables;

use Dom\Text;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
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
                TextColumn::make('level.name')
                    ->label('Nivel')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('enrollments_count')
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
                //
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
