<?php

namespace App\Filament\Resources\AcademicYears\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;

class AcademicYearsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('description')
                    ->label('Descrição')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('year')
                    ->numeric()
                    ->label('Ano')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('start_at')
                    ->date('d/m/Y')
                    ->label('Inicio'),
                TextColumn::make('end_at')
                    ->date('d/m/Y')
                    ->label('Fim'),
                ToggleColumn::make('is_default')
                    ->label('Padrão')

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
