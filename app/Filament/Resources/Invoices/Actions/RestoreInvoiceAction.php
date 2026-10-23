<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\Asaas\ChargeOperations;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/** Fatura cancelada volta a ser cobrada (e a cobrança excluída é restaurada no Asaas). */
class RestoreInvoiceAction
{
    public static function make(): Action
    {
        return Action::make('restoreInvoice')
            ->label('Restaurar fatura')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->authorize('update')
            ->visible(fn (Invoice $record): bool => $record->status === InvoiceStatus::Canceled)
            ->disabled(fn (Invoice $record): bool => ! ChargeOperations::canRestore($record))
            ->tooltip(fn (Invoice $record): ?string => ChargeOperations::canRestore($record)
                ? null
                : 'Fatura com pagamentos ou de matrícula cancelada não pode ser restaurada.')
            ->requiresConfirmation()
            ->modalHeading('Restaurar fatura')
            ->modalDescription(fn (Invoice $record): string => filled($record->asaas_invoice_id)
                ? 'A cobrança é restaurada no Asaas e a fatura volta a ser cobrada.'
                : 'A fatura volta a ser cobrada.')
            ->modalSubmitActionLabel('Restaurar')
            ->action(function (Action $action, Invoice $record, ChargeOperations $operations): void {
                RunsChargeOperation::run(
                    $action,
                    fn () => $operations->restore($record),
                    'Fatura restaurada',
                    fn (): string => 'Situação atual: '.$record->status->getLabel().'.',
                );
            });
    }
}
