<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Documents\PaymentReceipt;
use App\Filament\Actions\IssueDocumentAction;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Illuminate\Support\Number;

/** Recibo de um dos pagamentos da fatura. Se o pagamento já tem recibo, devolve o mesmo (2ª via). */
class IssuePaymentReceiptAction
{
    public static function make(): Action
    {
        return IssueDocumentAction::make(
            'issuePaymentReceipt',
            'Emitir recibo',
            fn (array $data, Invoice $record) => PaymentReceipt::issue(
                $record->payments()->findOrFail($data['payment_id']),
                auth()->user(),
            ),
            fields: [
                Select::make('payment_id')
                    ->label('Pagamento')
                    ->options(fn (Invoice $record): array => self::paymentOptions($record))
                    ->default(fn (Invoice $record): ?int => $record->payments()->count() === 1 ? $record->payments()->value('id') : null)
                    ->required()
                    ->native(false),
            ],
            withPurposeAndValidity: false,
        )
            ->icon('heroicon-o-receipt-percent')
            ->visible(fn (Invoice $record): bool => $record->payments()->where('amount', '>', 0)->exists())
            ->modalDescription('Cada pagamento tem um recibo numerado e com código de verificação. Se o pagamento escolhido já tem recibo, ele é reaberto para a 2ª via.');
    }

    /** "10/10/2026 · R$ 500,00 · PIX · recibo REC-2026-000001" */
    private static function paymentOptions(Invoice $record): array
    {
        return $record->payments()
            ->where('amount', '>', 0)
            ->orderBy('payment_date')
            ->get()
            ->mapWithKeys(fn (InvoicePayment $payment): array => [
                $payment->getKey() => collect([
                    $payment->payment_date?->format('d/m/Y'),
                    Number::currency((float) $payment->amount, 'BRL', 'pt_BR'),
                    $payment->payment_method?->getLabel(),
                    ($receipt = PaymentReceipt::existing($payment)) ? "recibo {$receipt->number}" : null,
                ])->filter()->implode(' · '),
            ])
            ->all();
    }
}
