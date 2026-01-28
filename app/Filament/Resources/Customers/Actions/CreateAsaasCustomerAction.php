<?php

namespace App\Filament\Resources\Customers\Actions;

use App\Models\Customer;
use App\Services\AsaasService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class CreateAsaasCustomerAction
{
    public static function make(): Action
    {
        return Action::make('create_asaas_customer')
            ->label('Sincronizar com Asaas')
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->tooltip('Criar ou atualizar cliente no Asaas')
            ->action(function (Customer $record) {
                try {
                    $asaasService = new AsaasService();
                    $result = $asaasService->createOrUpdateCustomer($record);

                    if (!$result) {
                        Notification::make()
                            ->title('Erro ao sincronizar')
                            ->body('Não foi possível sincronizar o cliente com Asaas. Verifique os dados e tente novamente.')
                            ->danger()
                            ->send();
                        return;
                    }

                    Notification::make()
                        ->title('Cliente sincronizado com sucesso!')
                        ->body('O cliente foi criado/atualizado no Asaas.')
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Erro ao sincronizar')
                        ->body('Erro: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            ->visible(fn (Customer $record) => $record->email !== null);
    }
}
