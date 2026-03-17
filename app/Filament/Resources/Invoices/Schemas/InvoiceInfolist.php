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
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('number')
                            ->label('Número da Fatura'),
                        TextEntry::make('reference')
                            ->label('Referência'),
                        TextEntry::make('customer.name')
                            ->label('Cliente'),
                        TextEntry::make('enrollment.id')
                            ->label('Inscrição'),
                        TextEntry::make('issue_date')
                            ->date('d/M/Y')
                            ->label('Data de Emissão'),
                        TextEntry::make('due_date')
                            ->date('d/M/Y')
                            ->label('Data de Vencimento'),
                        TextEntry::make('billing_type')
                            ->badge()
                            ->label('Tipo de Fatura'),
                        TextEntry::make('status')
                            ->badge()
                            ->label('Status da Fatura'),
                    ])->columns(4),

                Section::make('Período de Cobrança')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('billing_period_start')
                            ->date('d/M/Y')
                            ->label('Início do Período'),
                        TextEntry::make('billing_period_end')
                            ->date('d/M/Y')
                            ->label('Fim do Período'),
                    ])->columns(2),

                Section::make('Valores')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('original_amount')
                            ->money('BRL')
                            ->label('Valor Original'),
                        TextEntry::make('discount_type')
                            ->badge()
                            ->label('Tipo de Desconto'),
                        TextEntry::make('discount_source')
                            ->badge()
                            ->label('Origem do Desconto'),
                        TextEntry::make('discount_value')
                            ->money('BRL')
                            ->label('Valor do Desconto'),
                        TextEntry::make('amount')
                            ->money('BRL')
                            ->label('Valor Final'),
                        TextEntry::make('balance')
                            ->money('BRL')
                            ->label('Saldo Devido'),
                    ])->columns(3),

                Section::make('Notas e Observações')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('notes')
                            ->columnSpanFull()
                            ->label('Notas'),
                    ]),

                Section::make('Documentos')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('invoice_link')
                            ->url(fn($record) => $record->invoice_link)
                            ->openUrlInNewTab()
                            ->label('Link da Fatura'),
                        TextEntry::make('invoice_qrcode')
                            ->label('QR Code'),
                    ])->columns(2)
                    ->visible(fn($record) => $record->invoice_link || $record->invoice_qrcode),

                Section::make('ASAAS')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('asaas_invoice_id')
                            ->label('ID da Cobrança (ASAAS)'),
                        TextEntry::make('asaas_sync_status')
                            ->badge()
                            ->label('Status de Sincronização'),
                        TextEntry::make('asaas_synced_at')
                            ->dateTime('d/M/Y H:i')
                            ->label('Última Sincronização'),
                    ])->columns(3)
                    ->visible(fn($record) => $record->asaas_invoice_id),

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
                    ])->columns(4),

                Section::make('Informações do Sistema')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime('d/M/Y H:i')
                            ->label('Criado em'),
                        TextEntry::make('updated_at')
                            ->dateTime('d/M/Y H:i')
                            ->label('Atualizado em'),
                    ])->columns(2)
                    ->collapsed(),
            ]);
    }
}
