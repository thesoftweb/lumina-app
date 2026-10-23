<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Services\Asaas\ChargeOperationException;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Executa uma operação de fatura (ChargeOperations) numa ação do Filament: se o Asaas ou uma regra
 * recusar, mostra o motivo e mantém o modal aberto; nada foi alterado.
 */
class RunsChargeOperation
{
    /**
     * @param  Closure|string|null  $successBody  closure para montar o texto com a fatura já atualizada
     */
    public static function run(Action $action, Closure $operation, string $successTitle, Closure|string|null $successBody = null): mixed
    {
        try {
            $result = $operation();
        } catch (ChargeOperationException $e) {
            Notification::make()
                ->danger()
                ->title('Não foi possível concluir')
                ->body($e->getMessage())
                ->persistent()
                ->send();

            $action->halt();
        }

        Notification::make()
            ->success()
            ->title($successTitle)
            ->body($successBody instanceof Closure ? $successBody($result) : $successBody)
            ->send();

        return $result;
    }
}
