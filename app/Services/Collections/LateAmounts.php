<?php

namespace App\Services\Collections;

/** Valores de uma fatura vencida na data do cálculo (estimativa com as regras de multa e juros). */
final class LateAmounts
{
    public function __construct(
        /** Dias desde o vencimento da fatura. */
        public readonly int $daysLate,
        /** Dias de atraso da cobrança no Asaas (é sobre eles que correm os juros). */
        public readonly int $chargedDays,
        /** Valor sobre o qual incidem multa e juros (sem o desconto, que vale só até o vencimento). */
        public readonly float $base,
        public readonly float $fine,
        public readonly float $interest,
        public readonly float $total,
    ) {}

    public function charges(): float
    {
        return round($this->fine + $this->interest, 2);
    }
}
