<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\Asaas\ChargeOperations;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;

/** Cancela a fatura e exclui a cobrança no Asaas. Com pagamento, o recebimento tem de ser desfeito antes. */
class CancelInvoiceAction
{
    public static function make(): Action
    {
        return Action::make('cancelInvoice')
            ->label('Cancelar fatura')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->authorize('update')
            ->visible(fn (Invoice $record): bool => $record->status !== InvoiceStatus::Canceled)
            ->disabled(fn (Invoice $record): bool => ! ChargeOperations::canCancel($record))
            ->tooltip(fn (Invoice $record): ?string => ChargeOperations::canCancel($record)
                ? null
                : 'A fatura tem pagamento registrado: desfaça o recebimento antes de cancelar.')
            ->modalHeading('Cancelar fatura')
            ->modalDescription(fn (Invoice $record): string => ChargeOperations::hasActiveCharge($record)
                ? 'A cobrança é excluída no Asaas e o responsável deixa de ser cobrado. Dá para restaurar depois.'
                : 'A fatura deixa de ser cobrada. Dá para restaurar depois.')
            ->modalSubmitActionLabel('Cancelar fatura')
            ->schema([
                Textarea::make('reason')
                    ->label('Motivo do cancelamento')
                    ->required()
                    ->maxLength(500)
                    ->rows(3),
            ])
            ->action(function (Action $action, Invoice $record, array $data, ChargeOperations $operations): void {
                RunsChargeOperation::run(
                    $action,
                    fn () => $operations->cancel($record, $data['reason']),
                    'Fatura cancelada',
                );
            });
    }
}
