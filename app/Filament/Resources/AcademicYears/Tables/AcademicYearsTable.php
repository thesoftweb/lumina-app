<?php

namespace App\Filament\Resources\AcademicYears\Tables;

use App\Models\AcademicYear;
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
                    ->label('Padrão'),
                TextColumn::make('admissions_status')
                    ->label('Pré-matrícula')
                    ->state(fn (AcademicYear $record): string => $record->acceptsPreEnrollments() ? 'Recebendo' : ($record->admissions_open ? 'Fora do período' : 'Fechada'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Recebendo' => 'success',
                        'Fora do período' => 'warning',
                        default => 'gray',
                    }),

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
