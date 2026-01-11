<?php

namespace App\Filament\Resources\Enrollments\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EnrollmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.name')
                    ->label('Aluno'),
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
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('contract_print')
                    ->label('Imprimir Contrato')
                    ->icon('heroicon-o-document-text')
                    ->url(fn($record) => route('enrollments.contract.print', $record))
                    ->openUrlInNewTab(),
                Action::make('contract_pdf')
                    ->label('Baixar PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn($record) => route('enrollments.contract.pdf', $record))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
