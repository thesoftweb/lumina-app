<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Documents\PaymentReceipt;
use App\Enums\AsaasChargeState;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\Collections\LateAmounts;
use App\Services\Collections\LateFeeCalculator;
use App\Services\Collections\OverdueDebt;
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
                            ->helperText(fn (Invoice $record): ?string => collect([
                                $record->isPastDue() ? 'Vencida e com saldo' : null,
                                $record->asaas_due_date ? 'Cobrança no Asaas vence em '.$record->asaas_due_date->format('d/m/Y') : null,
                            ])->filter()->implode(' · ') ?: null),
                        // Vencida: quanto o responsável paga hoje (multa e juros estimados com as regras do Asaas)
                        TextEntry::make('late_charges')->label('Multa e juros até hoje')
                            ->state(fn (Invoice $record): float => self::lateAmounts($record)->charges())
                            ->money('BRL', locale: 'pt_BR')
                            ->helperText(fn (Invoice $record): ?string => self::lateDetails($record))
                            ->visible(fn (Invoice $record): bool => $record->isPastDue()),
                        TextEntry::make('updated_total')->label('Valor atualizado (estimado)')
                            ->state(fn (Invoice $record): float => self::lateAmounts($record)->total)
                            ->money('BRL', locale: 'pt_BR')->weight(FontWeight::SemiBold)->color('danger')
                            ->helperText('O valor exato está no link de pagamento do Asaas.')
                            ->visible(fn (Invoice $record): bool => $record->isPastDue()),
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

    private static function lateAmounts(Invoice $record): LateAmounts
    {
        return LateFeeCalculator::forCompany(Company::current(auth()->user()))->invoice($record);
    }

    /** "2% de multa (R$ 2,00) + juros de 13 dias (R$ 2,60), sobre R$ 100,00" */
    private static function lateDetails(Invoice $record): ?string
    {
        $calculator = LateFeeCalculator::forCompany(Company::current(auth()->user()));
        $amounts = $calculator->invoice($record);

        if ($amounts->chargedDays === 0) {
            return 'A cobrança no Asaas ainda não venceu.';
        }

        if ($calculator->rulesLabel() === null) {
            return 'Configure multa e juros nas configurações da empresa.';
        }

        return LateFeeCalculator::percent($calculator->finePercent).' de multa ('.OverdueDebt::money($amounts->fine).')'
            .' + '.LateFeeCalculator::percent($calculator->monthlyInterestPercent).' ao mês por '.$amounts->chargedDays.' dia(s) ('.OverdueDebt::money($amounts->interest).')'
            .', sobre '.OverdueDebt::money($amounts->base);
    }
}
