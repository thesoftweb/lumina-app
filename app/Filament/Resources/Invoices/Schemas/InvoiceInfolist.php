<?php

namespace App\Filament\Resources\Invoices\Schemas;

use Dom\Text;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detalhes da Fatura')
                    ->columnSpanFull(3)
                    ->schema([
                        TextEntry::make('number')
                            ->label('Número da Fatura'),
                        TextEntry::make('customer.name')
                            ->label('Cliente'),
                        TextEntry::make('issue_date')
                            ->date('d/M/Y')
                            ->label('Data de Emissão'),
                        TextEntry::make('due_date')
                            ->date('d/M/Y')
                            ->label('Data de Vencimento'),
                        TextEntry::make('amount')
                            ->money('BRL')
                            ->label('Valor Total'),
                        TextEntry::make('balance')
                            ->money('BRL')
                            ->label('Saldo Devido'),
                        TextEntry::make('status')
                            ->badge()
                            ->label('Status da Fatura'),
                        TextEntry::make('billing_type')
                            ->badge()
                            ->label('Tipo de Fatura'),
                        TextEntry::make('reference')
                            ->label('Referência'),
                        TextEntry::make('notes')
                            ->columnSpanFull()
                            ->label('Notas')
                    ])->columns(3),
                RepeatableEntry::make('payments')
                    ->label('Pagamentos Recebidos')
                    ->visible(fn($record) => $record->payments()->count() > 0)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('id')
                            ->label('ID do Pagamento'),
                        TextEntry::make('amount')
                            ->money('BRL')
                            ->label('Valor do Pagamento'),
                        TextEntry::make('payment_date')
                            ->date('d/M/Y')
                            ->label('Data do Pagamento'),
                        TextEntry::make('payment_method')
                            ->badge()
                            ->label('Método de Pagamento'),
                    ])->columns(4)
            ]);
    }
}
