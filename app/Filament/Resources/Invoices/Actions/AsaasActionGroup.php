<?php

namespace App\Filament\Resources\Invoices\Actions;

use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;

/** Ações do Asaas agrupadas num só botão do cabeçalho da fatura (some quando nenhuma se aplica). */
class AsaasActionGroup
{
    public static function make(): ActionGroup
    {
        return ActionGroup::make([
            GenerateAsaasChargeAction::make(),
            SyncAsaasPaymentAction::make(),
            ViewAsaasLinksAction::make(),
        ])
            ->label('Asaas')
            ->icon(Heroicon::OutlinedCreditCard)
            ->color('gray')
            ->button();
    }
}
