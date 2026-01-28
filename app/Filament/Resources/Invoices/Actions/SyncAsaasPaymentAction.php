<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Models\Invoice;
use App\Services\AsaasService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class SyncAsaasPaymentAction
{
    public static function make(): Action
    {
        return Action::make('sync_asaas_payment')
            ->label('Sincronizar Pagamento')
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->tooltip('Sincronizar status de pagamento do Asaas')
            ->action(function (Invoice $record) {
                if (!$record->asaas_invoice_id) {
                    Notification::make()
                        ->title('Cobrança não sincronizada')
                        ->body('Esta fatura ainda não foi enviada para o Asaas.')
                        ->warning()
                        ->send();
                    return;
                }

                $asaasService = new AsaasService();
                $result = $asaasService->syncPaymentStatus($record);

                if ($result) {
                    $record->refresh();
                    Notification::make()
                        ->title('✅ Status sincronizado!')
                        ->body("Status atual: {$record->status}")
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Erro ao sincronizar')
                        ->body('Não foi possível sincronizar com o Asaas. Tente novamente.')
                        ->danger()
                        ->send();
                }
            })
            ->visible(fn (Invoice $record) => $record->asaas_invoice_id !== null);
    }
}
