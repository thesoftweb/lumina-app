<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Enums\EnrollmentStatus;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\Asaas\ChargeOperations;
use Filament\Actions\Action;

/** Vincula uma cobrança do Asaas a uma fatura que ainda não tem (faturas antigas). */
class GenerateAsaasChargeAction
{
    public static function make(): Action
    {
        return Action::make('generate_asaas_charge')
            ->label('Gerar Cobrança Asaas')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('primary')
            ->tooltip('Enviar esta fatura para o Asaas e gerar boleto/PIX')
            ->authorize('update')
            ->requiresConfirmation()
            ->modalHeading('Gerar Cobrança no Asaas')
            ->modalDescription('Esta fatura será enviada para o Asaas e gerará boleto e PIX dinâmico.')
            ->modalSubmitActionLabel('Gerar')
            ->action(function (Action $action, Invoice $record, ChargeOperations $operations): void {
                RunsChargeOperation::run(
                    $action,
                    fn () => $operations->attachCharge($record),
                    'Cobrança gerada no Asaas',
                    fn (): string => "ID Asaas: {$record->asaas_invoice_id}",
                );
            })
            ->visible(fn (Invoice $record) => ! $record->asaas_invoice_id
                && ! in_array($record->status, [InvoiceStatus::Paid, InvoiceStatus::Canceled], true)
                && $record->enrollment?->status !== EnrollmentStatus::Canceled);
    }
}
