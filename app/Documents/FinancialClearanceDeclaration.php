<?php

namespace App\Documents;

use App\Enums\IssuedDocumentType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\IssuedDocument;
use App\Models\User;
use App\Services\DocumentIssuer;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

/**
 * Declaração de que o responsável financeiro não tem débitos com a escola. Duas formas:
 * - até a data da emissão: nenhuma fatura vencida com saldo a pagar;
 * - de um ano já encerrado (quitação anual, Lei nº 12.007/2009): todas as faturas daquele ano pagas.
 * Só enxerga o que está registrado nas faturas do sistema.
 */
class FinancialClearanceDeclaration
{
    /** @return Collection<int, Invoice> */
    public static function pendingInvoices(Customer $customer, ?int $year = null): Collection
    {
        return Invoice::query()
            ->where('customer_id', $customer->getKey())
            ->when(
                $year,
                fn ($query) => $query->unpaid()->whereYear('due_date', $year),
                fn ($query) => $query->overdue(),
            )
            ->orderBy('due_date')
            ->get();
    }

    public static function describePending(Collection $invoices): string
    {
        $list = $invoices->take(5)->map(fn (Invoice $invoice): string => sprintf(
            '%s (venc. %s, saldo %s)',
            $invoice->reference ?: "Fatura {$invoice->getKey()}",
            $invoice->due_date->format('d/m/Y'),
            Number::currency((float) $invoice->balance, 'BRL', 'pt_BR'),
        ))->implode('; ');

        $more = $invoices->count() > 5 ? sprintf(' e mais %d', $invoices->count() - 5) : '';

        return trans_choice('{1} Há :count fatura com saldo a pagar|[2,*] Há :count faturas com saldo a pagar', $invoices->count())
            .": {$list}{$more}. Registre os pagamentos antes de emitir a declaração.";
    }

    /** Anos encerrados que podem receber a quitação anual (do mais recente ao mais antigo). */
    public static function closedYears(): array
    {
        $current = (int) today()->year;

        return collect(range($current - 1, $current - 5))->mapWithKeys(fn (int $year): array => [$year => (string) $year])->all();
    }

    public static function issue(Customer $customer, User $issuer, ?int $year = null, ?string $purpose = null, ?int $validForDays = null): IssuedDocument
    {
        if ($year !== null && $year >= (int) today()->year) {
            throw ValidationException::withMessages(['year' => 'A quitação anual só pode ser emitida para um ano já encerrado.']);
        }

        $pending = self::pendingInvoices($customer, $year);

        if ($pending->isNotEmpty()) {
            throw ValidationException::withMessages(['invoices' => self::describePending($pending)]);
        }

        return app(DocumentIssuer::class)->issue(
            type: IssuedDocumentType::FinancialClearanceDeclaration,
            documentable: $customer,
            subject: $customer->name,
            content: [
                'customer' => ['name' => $customer->name, 'cpf' => $customer->document],
                'students' => $customer->students()->orderBy('name')->pluck('name')->all(),
                'period' => ['year' => $year, 'until' => today()->format('d/m/Y')],
            ],
            issuer: $issuer,
            purpose: $purpose,
            validUntil: $validForDays ? today()->addDays($validForDays) : null,
        );
    }
}
