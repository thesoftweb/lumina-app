<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Models\Invoice;
use App\Services\Asaas\ChargeOperations;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Excluir fatura (só admin, pela policy). Fatura com cobrança ativa no Asaas não é excluída: apagar
 * aqui deixaria o responsável sendo cobrado lá. Para ela existe "Cancelar fatura".
 */
class DeleteInvoiceActions
{
    public static function single(): DeleteAction
    {
        return DeleteAction::make()
            ->label('Excluir fatura')
            ->visible(fn (Invoice $record): bool => ! ChargeOperations::hasActiveCharge($record));
    }

    public static function bulk(): DeleteBulkAction
    {
        return DeleteBulkAction::make()
            ->before(function (DeleteBulkAction $action, Collection $records): void {
                $blocked = $records->filter(fn (Invoice $invoice): bool => ChargeOperations::hasActiveCharge($invoice));

                if ($blocked->isEmpty()) {
                    return;
                }

                Notification::make()
                    ->danger()
                    ->title('Nenhuma fatura foi excluída')
                    ->body('Faturas com cobrança no Asaas não podem ser excluídas (nº '
                        .$blocked->map(fn (Invoice $invoice): string => (string) ($invoice->number ?? $invoice->getKey()))->implode(', ')
                        .'). Use "Cancelar fatura" nelas, que também exclui a cobrança no Asaas.')
                    ->persistent()
                    ->send();

                $action->halt();
            });
    }
}
