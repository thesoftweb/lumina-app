<?php

namespace App\Services\Asaas;

use App\Models\Customer;
use App\Models\Invoice;

/** Resultado da tentativa de importar como fatura uma cobrança criada direto no Asaas. */
class ImportOutcome
{
    public function __construct(
        public readonly bool $imported,
        public readonly ?Customer $customer = null,
        public readonly ?Invoice $invoice = null,
        public readonly ?SyncResult $sync = null,
        public readonly ?string $reason = null,
        public readonly bool $dryRun = false,
    ) {}

    public static function skipped(string $reason, ?Customer $customer = null): self
    {
        return new self(imported: false, customer: $customer, reason: $reason);
    }
}
