<?php

namespace App\Filament\Resources\LessonPlans\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class LessonPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->sortable()
                    ->searchable()
                    ->label('Título da Aula'),

                TextColumn::make('classroom.name')
                    ->sortable()
                    ->searchable()
                    ->label('Turma'),

                TextColumn::make('subject.name')
                    ->sortable()
                    ->searchable()
                    ->label('Disciplina'),

                TextColumn::make('scheduled_date')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->label('Data Agendada'),

                BadgeColumn::make('status')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'draft' => 'Rascunho',
                        'scheduled' => 'Agendado',
                        'completed' => 'Concluído',
                        'cancelled' => 'Cancelado',
                        default => $state,
                    })
                    ->color(fn($state) => match ($state) {
                        'draft' => 'gray',
                        'scheduled' => 'blue',
                        'completed' => 'green',
                        'cancelled' => 'red',
                        default => 'gray',
                    })
                    ->sortable()
                    ->label('Status'),
            ])
            ->filters([
                SelectFilter::make('classroom_id')
                    ->relationship('classroom', 'name')
                    ->label('Turma'),

                SelectFilter::make('subject_id')
                    ->relationship('subject', 'name')
                    ->label('Disciplina'),

                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Rascunho',
                        'scheduled' => 'Agendado',
                        'completed' => 'Concluído',
                        'cancelled' => 'Cancelado',
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
