<?php

namespace App\Services\Collections;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Collection;

/** Faturas vencidas de um responsável, com o valor atualizado de cada uma. */
final class OverdueDebt
{
    /** @param  Collection<int, array{invoice: Invoice, amounts: LateAmounts}>  $items */
    public function __construct(
        public readonly Customer $customer,
        public readonly Collection $items,
        public readonly LateFeeCalculator $calculator,
    ) {}

    public static function for(Customer $customer, LateFeeCalculator $calculator): self
    {
        $invoices = $customer->invoices()->overdue()
            ->with('enrollment.student')
            ->orderBy('due_date')->orderBy('id')
            ->get();

        return new self(
            $customer,
            $invoices->map(fn (Invoice $invoice): array => ['invoice' => $invoice, 'amounts' => $calculator->invoice($invoice)]),
            $calculator,
        );
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    public function count(): int
    {
        return $this->items->count();
    }

    /** Saldo das faturas vencidas, sem multa e juros. */
    public function balance(): float
    {
        return round($this->items->sum(fn (array $item): float => (float) $item['invoice']->balance), 2);
    }

    /** Saldo com multa e juros (estimado). */
    public function updatedTotal(): float
    {
        return round($this->items->sum(fn (array $item): float => $item['amounts']->total), 2);
    }

    public function charges(): float
    {
        return round($this->items->sum(fn (array $item): float => $item['amounts']->charges()), 2);
    }

    public function maxDaysLate(): int
    {
        return (int) $this->items->max(fn (array $item): int => $item['amounts']->daysLate);
    }

    /** @return list<int> */
    public function invoiceIds(): array
    {
        return $this->items->map(fn (array $item): int => $item['invoice']->id)->values()->all();
    }

    /** "Mensalidade outubro/2026" */
    public static function invoiceLabel(Invoice $invoice): string
    {
        return trim(($invoice->billing_type?->getLabel() ?? 'Fatura').' '.$invoice->competenceLabel());
    }

    public static function money(float|string|null $value): string
    {
        return 'R$ '.number_format((float) $value, 2, ',', '.');
    }
}
