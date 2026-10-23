<?php

namespace App\Services\Asaas;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentType;
use Carbon\CarbonInterface;

/**
 * Descrição do que a sincronização de uma cobrança fez com a fatura: status antes e depois, pagamento
 * registrado, campos atualizados e avisos. É o que o relatório da conciliação imprime.
 */
class SyncResult
{
    /**
     * @param  list<string>  $updates  ex.: "valor: R$ 100,00 → R$ 120,00"
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly int $invoiceId,
        public readonly string $asaasPaymentId,
        public readonly ?string $asaasStatus,
        public readonly SyncChange $change,
        public readonly ?InvoiceStatus $statusBefore,
        public readonly ?InvoiceStatus $statusAfter,
        public readonly bool $dryRun = false,
        public readonly bool $paymentCreated = false,
        public readonly ?PaymentType $paymentMethod = null,
        public readonly ?CarbonInterface $paymentDate = null,
        public readonly ?float $paymentAmount = null,
        public readonly int $paymentsReversed = 0,
        public readonly array $updates = [],
        public readonly array $warnings = [],
    ) {}

    /** A fatura muda (ou mudaria, na simulação): status, pagamento, estorno ou valores. */
    public function changed(): bool
    {
        return ! in_array($this->change, [SyncChange::Unchanged, SyncChange::Attention, SyncChange::Unknown], true);
    }

    /** O status da cobrança foi entendido; status novo/desconhecido devolve false. */
    public function recognized(): bool
    {
        return $this->change !== SyncChange::Unknown;
    }

    public function needsAttention(): bool
    {
        return $this->warnings !== [];
    }
}
