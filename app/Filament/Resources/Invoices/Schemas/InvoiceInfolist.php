<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Documents\PaymentReceipt;
use App\Enums\AsaasChargeState;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

/** Resumo compacto da fatura: usado no painel lateral da listagem e na página de visualizar. */
class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Resumo')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('status')->label('Situação')->badge(),
                        TextEntry::make('billing_type')->label('Tipo de cobrança')->badge(),
                        TextEntry::make('amount')->label('Valor')
                            ->money('BRL', locale: 'pt_BR')->weight(FontWeight::SemiBold),
                        TextEntry::make('balance')->label('Saldo a pagar')
                            ->money('BRL', locale: 'pt_BR')->weight(FontWeight::SemiBold)
                            ->color(fn (Invoice $record): string => (float) $record->balance > 0 ? 'warning' : 'success'),
                        TextEntry::make('discount_value')->label('Desconto aplicado')
                            ->money('BRL', locale: 'pt_BR')
                            ->visible(fn (Invoice $record): bool => (float) $record->discount_value > 0),
                        TextEntry::make('original_amount')->label('Valor sem desconto')
                            ->money('BRL', locale: 'pt_BR')
                            ->visible(fn (Invoice $record): bool => (float) $record->discount_value > 0),
                        TextEntry::make('due_date')->label('Vencimento')->date('d/m/Y')
                            ->color(fn (Invoice $record): ?string => $record->isPastDue() ? 'danger' : null)
                            ->helperText(fn (Invoice $record): ?string => $record->isPastDue() ? 'Vencida e com saldo' : null),
                        TextEntry::make('competence')->label('Competência')
                            ->state(fn (Invoice $record): ?string => $record->competenceLabel()),
                        TextEntry::make('customer.name')->label('Responsável')
                            ->color('primary')
                            ->url(fn (Invoice $record): ?string => $record->customer
                                ? CustomerResource::getUrl('edit', ['record' => $record->customer])
                                : null)
                            ->columnSpanFull(),
                        TextEntry::make('enrollment.student.name')->label('Aluno')->placeholder('Sem matrícula vinculada'),
                        TextEntry::make('enrollment.classroom.name')->label('Turma')->placeholder('-'),
                        TextEntry::make('reference')->label('Referência interna')->placeholder('-')
                            ->color('gray')->size('sm'),
                        TextEntry::make('issue_date')->label('Emissão')->date('d/m/Y')->placeholder('-'),
                    ]),

                Section::make('Pagamentos recebidos')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->schema([
                        RepeatableEntry::make('payments')
                            ->hiddenLabel()
                            ->placeholder('Nenhum pagamento registrado.')
                            ->table([
                                TableColumn::make('Data'),
                                TableColumn::make('Valor'),
                                TableColumn::make('Forma'),
                                TableColumn::make('Recibo'),
                            ])
                            ->schema([
                                TextEntry::make('payment_date')->date('d/m/Y'),
                                TextEntry::make('amount')->money('BRL', locale: 'pt_BR'),
                                TextEntry::make('payment_method')->placeholder('-'),
                                TextEntry::make('receipt')
                                    ->state(fn (InvoicePayment $record): ?string => PaymentReceipt::existing($record)?->number)
                                    ->placeholder('Não emitido')
                                    ->url(fn (InvoicePayment $record): ?string => ($receipt = PaymentReceipt::existing($record))
                                        ? route('issued-documents.print', $receipt)
                                        : null)
                                    ->openUrlInNewTab()
                                    ->color('primary'),
                            ]),
                    ]),

                Section::make('Asaas')
                    ->icon(Heroicon::OutlinedCreditCard)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('asaas_state')->label('Situação')
                            ->state(fn (Invoice $record): AsaasChargeState => AsaasChargeState::of($record))
                            ->badge()
                            ->columnSpanFull(),
                        TextEntry::make('asaas_invoice_id')->label('ID da cobrança')
                            ->copyable()->placeholder('Sem cobrança'),
                        TextEntry::make('asaas_synced_at')->label('Última sincronização')
                            ->dateTime('d/m/Y H:i')->placeholder('-'),
                        TextEntry::make('invoice_link')->label('Link da fatura')
                            ->state(fn (Invoice $record): ?string => $record->invoice_link ? 'Abrir fatura no Asaas' : null)
                            ->url(fn (Invoice $record): ?string => $record->invoice_link)
                            ->openUrlInNewTab()
                            ->color('primary')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),

                Section::make('Observações')
                    ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                    ->visible(fn (Invoice $record): bool => filled($record->notes))
                    ->schema([
                        TextEntry::make('notes')->hiddenLabel()->prose(),
                    ]),
            ]);
    }
}
