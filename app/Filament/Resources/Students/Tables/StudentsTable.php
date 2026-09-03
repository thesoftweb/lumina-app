<?php

namespace App\Filament\Resources\Students\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('name')
                    ->description(fn ($record) => $record->customer ? $record->customer->name : null)
                    ->label('Nome')->searchable()->sortable(),
                TextColumn::make('date_of_birth')->label('Data de Nascimento')->date('d/m/Y')->searchable()->sortable(),
                TextColumn::make('affiliation_1')->label('Filiação 1')->searchable()->sortable(),
                TextColumn::make('affiliation_2')->label('Filiação 2')->searchable()->sortable(),
                TextColumn::make('enrollments.classroom.name')->label('Turmas')->badge(),
                TextColumn::make('observations')->label('Observações')->limit(50)->wrap(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn (): bool => ! Auth::user()->hasRole('teacher')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ])->visible(fn (): bool => ! Auth::user()->hasRole('teacher')),
            ]);
    }
}
