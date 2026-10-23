<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\Asaas\ChargeOperations;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;

/** Desfaz um recebimento (também no Asaas, quando a cobrança foi baixada); o recibo é cancelado. */
class UndoPaymentAction
{
    public static function make(): Action
    {
        return Action::make('undoPayment')
            ->label('Desfazer recebimento')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->authorize('update')
            ->visible(fn (Invoice $record): bool => ChargeOperations::canUndo($record))
            ->modalHeading('Desfazer recebimento')
            ->modalDescription(fn (Invoice $record): string => ChargeOperations::hasActiveCharge($record)
                ? 'O recebimento também é desfeito no Asaas (a cobrança volta a ser cobrada) e o recibo do pagamento é cancelado.'
                : 'O pagamento é apagado, o saldo volta para a fatura e o recibo do pagamento é cancelado.')
            ->modalSubmitActionLabel('Desfazer')
            ->schema([
                Select::make('payment_id')
                    ->label('Pagamento')
                    ->options(fn (Invoice $record): array => ChargeOperations::undoablePayments($record)
                        ->mapWithKeys(fn (InvoicePayment $payment): array => [$payment->getKey() => collect([
                            $payment->payment_date?->format('d/m/Y'),
                            Number::currency((float) $payment->amount, 'BRL', 'pt_BR'),
                            $payment->payment_method?->getLabel(),
                        ])->filter()->implode(' · ')])
                        ->all())
                    ->default(fn (Invoice $record): ?int => ($payments = ChargeOperations::undoablePayments($record))->count() === 1 ? $payments->first()->getKey() : null)
                    ->required()
                    ->native(false),
            ])
            ->action(function (Action $action, Invoice $record, array $data, ChargeOperations $operations): void {
                RunsChargeOperation::run(
                    $action,
                    fn () => $operations->undoPayment($record, $record->payments()->findOrFail($data['payment_id'])),
                    'Recebimento desfeito',
                    fn (): string => 'Situação atual: '.$record->status->getLabel().'.',
                );
            });
    }
}
