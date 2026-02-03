<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Models\Invoice;
use App\Services\AsaasService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class GenerateAsaasChargeAction
{
    public static function make(): Action
    {
        return Action::make('generate_asaas_charge')
            ->label('Gerar Cobrança Asaas')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('primary')
            ->tooltip('Enviar esta fatura para o Asaas e gerar boleto/PIX')
            ->requiresConfirmation()
            ->modalHeading('Gerar Cobrança no Asaas')
            ->modalSubheading('Esta fatura será enviada para o Asaas e gerará boleto e PIX dinâmico.')
            ->modalButton('Gerar')
            ->action(function (Invoice $record) {
                $asaasService = new AsaasService();

                // Sincronizar cliente se necessário
                if (!$record->customer->asaas_customer_id) {
                    $result = $asaasService->createOrUpdateCustomer($record->customer);
                    if (!$result) {
                        Notification::make()
                            ->title('Erro ao sincronizar cliente')
                            ->body('Não foi possível registrar o cliente no Asaas. Verifique as credenciais.')
                            ->danger()
                            ->send();
                        return;
                    }
                }

                // Criar cobrança
                $result = $asaasService->createCharge($record);

                if ($result && isset($result['id'])) {
                    Notification::make()
                        ->title('✅ Cobrança gerada com sucesso!')
                        ->body("ID Asaas: {$result['id']}\nReferência: {$record->reference}")
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Erro ao gerar cobrança')
                        ->body('Não foi possível enviar a cobrança para o Asaas. Tente novamente.')
                        ->danger()
                        ->send();
                }
            })
            ->visible(fn (Invoice $record) => !$record->asaas_invoice_id && $record->status !== 'paid' && $record->status !== 'canceled');
    }
}
