<?php

namespace App\Filament\Resources\ClassDiaries\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ClassDiariesTable
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

                TextColumn::make('subject.name')
                    ->sortable()
                    ->searchable()
                    ->label('Disciplina'),

                TextColumn::make('teacher.name')
                    ->sortable()
                    ->searchable()
                    ->label('Professor'),

                TextColumn::make('content')
                    ->limit(50)
                    ->html()
                    ->label('Conteúdo'),
            ])
            ->filters([
                SelectFilter::make('classroom_id')
                    ->relationship('classroom', 'name')
                    ->label('Turma'),

                SelectFilter::make('subject_id')
                    ->relationship('subject', 'name')
                    ->label('Disciplina'),

                Filter::make('date')
                    ->form([
                        // DateRangeFilter would go here
                    ])
                    ->query(function ($query, array $data) {
                        return $query;
                    })
                    ->label('Período'),

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
