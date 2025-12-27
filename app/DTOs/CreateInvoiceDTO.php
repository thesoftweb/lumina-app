<?php

namespace App\DTOs;

use App\Enums\InvoiceType;
use Carbon\Carbon;

class CreateInvoiceDTO
{
    public function __construct(
        public readonly int $customer_id,
        public readonly float $amount,
        public readonly InvoiceType $type,
        public readonly ?Carbon $issue_date = null,
        public readonly ?Carbon $due_date = null,
        public readonly ?int $company_id = null,
        public readonly ?int $account_id = null,
        public readonly ?string $reference = null,
        public readonly ?string $number = null,
        public readonly ?Carbon $billing_period_start = null,
        public readonly ?Carbon $billing_period_end = null,
        public readonly ?string $notes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            customer_id: $data['customer_id'],
            amount: $data['amount'],
            type: InvoiceType::from($data['type']),
            issue_date: isset($data['issue_date']) ? Carbon::parse($data['issue_date']) : null,
            due_date: isset($data['due_date']) ? Carbon::parse($data['due_date']) : null,
            company_id: $data['company_id'] ?? null,
            account_id: $data['account_id'] ?? null,
            reference: $data['reference'] ?? null,
            number: $data['number'] ?? null,
            billing_period_start: isset($data['billing_period_start']) ? Carbon::parse($data['billing_period_start']) : null,
            billing_period_end: isset($data['billing_period_end']) ? Carbon::parse($data['billing_period_end']) : null,
            notes: $data['notes'] ?? null,
        );
    }
}
