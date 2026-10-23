<?php

namespace App\Enums;

use App\Models\Invoice;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Situação da fatura em relação ao Asaas, para a tela: junta o id da cobrança e o
 * asaas_sync_status num só indicador (com cobrança, pendente, falha, cancelada ou sem cobrança).
 */
enum AsaasChargeState: string implements HasColor, HasIcon, HasLabel
{
    case Synced = 'synced';
    case Pending = 'pending';
    case Failed = 'failed';
    case Canceled = 'canceled';
    case None = 'none';

    public static function of(Invoice $invoice): self
    {
        // A falha vem primeiro: pode acontecer antes de existir o id (cobrança não criada)
        if ($invoice->asaas_sync_status === 'failed') {
            return self::Failed;
        }

        if (blank($invoice->asaas_invoice_id)) {
            return self::None;
        }

        return match ($invoice->asaas_sync_status) {
            'pending' => self::Pending,
            'canceled' => self::Canceled,
            default => self::Synced,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Synced => 'Cobrança no Asaas sincronizada',
            self::Pending => 'Sincronização com o Asaas pendente',
            self::Failed => 'Falha na sincronização com o Asaas',
            self::Canceled => 'Cobrança cancelada no Asaas',
            self::None => 'Sem cobrança no Asaas',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Synced => 'success',
            self::Pending => 'warning',
            self::Failed => 'danger',
            self::Canceled, self::None => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Synced => Heroicon::OutlinedCheckCircle,
            self::Pending => Heroicon::OutlinedClock,
            self::Failed => Heroicon::OutlinedExclamationTriangle,
            self::Canceled => Heroicon::OutlinedXCircle,
            self::None => Heroicon::OutlinedMinusCircle,
        };
    }
}
