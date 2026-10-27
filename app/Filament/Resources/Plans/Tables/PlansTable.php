<?php

namespace App\Filament\Resources\Plans\Tables;

use App\Models\AcademicYear;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Descrição')
                    ->searchable(),
                TextColumn::make('academicYear.description')
                    ->label('Ano letivo')
                    ->placeholder('Sem ano')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('base_amount')
                    ->label('Valor Base')
                    ->money('BRL')
                    ->searchable(),
                TextColumn::make('final_value')
                    ->label('Valor final')
                    ->money('BRL')
                    ->searchable()
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
