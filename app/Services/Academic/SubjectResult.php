<?php

namespace App\Services\Academic;

/** Resultado anual de uma disciplina da matrícula. */
final class SubjectResult
{
    public const APPROVED = 'approved';

    public const FAILED = 'failed';

    public const PENDING = 'pending';

    /**
     * @param  array<int, string|null>  $termGrades  nota ("8,50") ou conceito de cada bimestre, pelo id do bimestre
     */
    public function __construct(
        public readonly int $assignmentId,
        public readonly string $name,
        public readonly ?int $workload,
        public readonly array $termGrades,
        /** Média anual (modo numérico, só com todos os bimestres lançados). */
        public readonly ?float $average,
        /** Média dos bimestres já lançados (para o ano em curso). */
        public readonly ?float $partialAverage,
        /** Conceito final: o do último bimestre (modo conceitual). */
        public readonly ?string $concept,
        public readonly string $status,
    ) {}

    /** Nota final ("8,50") ou conceito; "—" sem resultado. */
    public function finalLabel(): string
    {
        return match (true) {
            $this->average !== null => number_format($this->average, 2, ',', '.'),
            $this->concept !== null => $this->concept,
            default => '—',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::APPROVED => 'Aprovado',
            self::FAILED => 'Reprovado',
            default => 'Em andamento',
        };
    }
}
