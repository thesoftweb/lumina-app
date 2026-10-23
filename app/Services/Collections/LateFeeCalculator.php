<?php

namespace App\Services\Collections;

use App\Models\Company;
use App\Models\Invoice;
use Carbon\CarbonInterface;

/**
 * Estima o valor atualizado de uma fatura vencida com as mesmas regras configuradas no Asaas:
 * multa única sobre o valor e juros ao mês cobrados proporcionalmente por dia de atraso. O valor
 * exato é o que o Asaas mostra no link da fatura; aqui é para a secretaria saber quanto cobrar.
 *
 * Depois do vencimento o desconto deixa de valer: a base é o valor cheio. Fatura com pagamento
 * parcial tem a cobrança no Asaas valendo só o saldo, sem desconto: a base é o saldo.
 */
class LateFeeCalculator
{
    public function __construct(
        public readonly float $finePercent,
        public readonly float $monthlyInterestPercent,
    ) {}

    public static function forCompany(?Company $company): self
    {
        return new self((float) ($company?->late_fine_percent ?? 0), (float) ($company?->late_interest_monthly_percent ?? 0));
    }

    public function invoice(Invoice $invoice, ?CarbonInterface $on = null): LateAmounts
    {
        $on = ($on ?? today())->copy()->startOfDay();
        $balance = round((float) $invoice->balance, 2);
        $daysLate = $this->daysAfter($invoice->due_date, $on);

        // Fatura gerada depois do vencimento: a cobrança no Asaas vence depois e só então corre atraso
        $chargedDays = $this->daysAfter($invoice->asaas_due_date ?? $invoice->due_date, $on);

        if ($chargedDays === 0) {
            return new LateAmounts($daysLate, 0, $balance, 0, 0, $balance);
        }

        $partial = $balance < round((float) $invoice->amount, 2) - 0.005;
        $base = $partial ? $balance : round((float) ($invoice->original_amount ?? $invoice->amount), 2);

        $fine = round($base * $this->finePercent / 100, 2);
        $interest = round($base * $this->monthlyInterestPercent / 100 / 30 * $chargedDays, 2);

        return new LateAmounts($daysLate, $chargedDays, $base, $fine, $interest, round($base + $fine + $interest, 2));
    }

    /** "2% de multa + 6% ao mês de juros" (vazio quando não há regras configuradas). */
    public function rulesLabel(): ?string
    {
        $parts = array_filter([
            $this->finePercent > 0 ? self::percent($this->finePercent).' de multa' : null,
            $this->monthlyInterestPercent > 0 ? self::percent($this->monthlyInterestPercent).' ao mês de juros' : null,
        ]);

        return $parts === [] ? null : implode(' + ', $parts);
    }

    public static function percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',').'%';
    }

    private function daysAfter(?CarbonInterface $date, CarbonInterface $on): int
    {
        if ($date === null || ! $date->copy()->startOfDay()->lt($on)) {
            return 0;
        }

        return (int) $date->copy()->startOfDay()->diffInDays($on);
    }
}
