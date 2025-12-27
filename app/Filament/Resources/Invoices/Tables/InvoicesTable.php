<?php

namespace App\Filament\Resources\Invoices\Tables;

use Dom\Text;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('Número')->sortable()->searchable(),
                TextColumn::make('customer.name')->label('Cliente')->sortable()->searchable(),
                TextColumn::make('amount')->label('Valor')->money('BRL')->sortable(),
                TextColumn::make('status')->label('Status')->badge()->sortable()->searchable(),
                TextColumn::make('balance')->label('Saldo')->money('BRL')->sortable(),
                TextColumn::make('billing_type')->label('Tipo')->badge()->sortable()->searchable(),
                TextColumn::make('due_date')->label('Data de Vencimento')->date('d/m/Y')->sortable(),
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
