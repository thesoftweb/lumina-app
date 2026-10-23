<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Asaas\PaymentSynchronizer;
use App\Services\Asaas\SyncChange;
use App\Services\Asaas\SyncResult;
use App\Services\AsaasService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Number;

/**
 * Concilia as faturas com as cobranças do Asaas: percorre as cobranças lá (todas as páginas), acha a
 * fatura de cada uma e aplica a situação pelo PaymentSynchronizer. Corrige, por exemplo, faturas
 * recebidas em dinheiro e baixadas no Asaas (RECEIVED_IN_CASH) que seguiam abertas no sistema.
 *
 * --since filtra pelo VENCIMENTO da cobrança (dueDate >= data), não pela criação: assim entram as
 * cobranças antigas que foram pagas ou baixadas depois, desde que vençam a partir da data.
 */
class ReconcileAsaasPayments extends Command
{
    protected $signature = 'asaas:reconcile
                            {--dry-run : Só mostra o que mudaria, sem gravar nada}
                            {--since= : Só cobranças com vencimento a partir desta data (AAAA-MM-DD)}
                            {--status= : Só cobranças neste status do Asaas (ex.: RECEIVED_IN_CASH)}';

    protected $description = 'Concilia as faturas com as cobranças do Asaas e imprime o relatório do que mudou';

    /** @var array<string, ?string> nome local do cliente por id de cliente no Asaas */
    private array $customerNames = [];

    public function handle(AsaasService $asaas, PaymentSynchronizer $synchronizer): int
    {
        if (! config('asaas.api_key')) {
            $this->error('ASAAS_API_KEY não configurada no .env');

            return self::FAILURE;
        }

        $filters = $this->filters();

        if ($filters === null) {
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('SIMULAÇÃO (--dry-run): nada será gravado.');
        }

        $this->info('Lendo as cobranças do Asaas'.($filters ? ' ('.http_build_query($filters, '', ', ').')' : '').'...');

        /** @var list<array{invoice: Invoice, result: SyncResult}> $results */
        $results = [];
        $orphans = [];
        $warnings = [];
        $errors = [];
        $read = 0;
        $events = 0;

        try {
            foreach ($asaas->paginatePayments($filters) as $payment) {
                $read++;
                $reference = (string) ($payment['externalReference'] ?? '');

                // Cobranças de eventos têm fluxo próprio (EventParticipant)
                if (str_starts_with($reference, 'EVENT-')) {
                    $events++;

                    continue;
                }

                $invoice = $this->findInvoice($payment);

                if (! $invoice) {
                    // Excluída lá e inexistente aqui: nada a importar
                    if (! ($payment['deleted'] ?? false)) {
                        $orphans[] = $payment;
                    }

                    continue;
                }

                // A fatura aponta para outra cobrança (ex.: cobrança gerada de novo): esta não manda nela
                if ($invoice->asaas_invoice_id && $invoice->asaas_invoice_id !== $payment['id']) {
                    if (! ($payment['deleted'] ?? false)) {
                        $warnings[] = [$this->invoiceLabel($invoice), $payment['id'], "Cobrança ({$payment['status']}) referencia esta fatura, mas a fatura está ligada à cobrança {$invoice->asaas_invoice_id}; ignorada."];
                    }

                    continue;
                }

                try {
                    $result = $synchronizer->apply($invoice, $payment, $dryRun);
                } catch (\Throwable $e) {
                    $errors[] = "Fatura {$this->invoiceLabel($invoice)} / cobrança {$payment['id']}: {$e->getMessage()}";

                    continue;
                }

                $results[] = ['invoice' => $invoice, 'result' => $result];

                foreach ($result->warnings as $warning) {
                    $warnings[] = [$this->invoiceLabel($invoice), $payment['id'], $warning];
                }
            }
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            $this->line('Conciliação interrompida; o que já foi aplicado acima permanece.');

            return self::FAILURE;
        }

        $this->report($results, $orphans, $warnings, $errors, $read, $events, $dryRun);

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<string, string>|null */
    private function filters(): ?array
    {
        $filters = [];

        if ($since = $this->option('since')) {
            if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $since, $parts) || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                $this->error('--since deve estar no formato AAAA-MM-DD.');

                return null;
            }

            $filters['dueDate[ge]'] = $since;
        }

        if ($status = $this->option('status')) {
            $status = strtoupper($status);
            $known = [
                ...PaymentSynchronizer::PAID_STATUSES,
                ...PaymentSynchronizer::PENDING_STATUSES,
                ...PaymentSynchronizer::OVERDUE_STATUSES,
                PaymentSynchronizer::REFUNDED_STATUS,
                ...array_keys(PaymentSynchronizer::DISPUTE_STATUSES),
            ];

            if (! in_array($status, $known, true)) {
                $this->error("Status desconhecido: {$status}. Use um de: ".implode(', ', $known));

                return null;
            }

            $filters['status'] = $status;
        }

        return $filters;
    }

    /** @param  array<string, mixed>  $payment */
    private function findInvoice(array $payment): ?Invoice
    {
        $invoice = Invoice::query()->with('customer')->where('asaas_invoice_id', $payment['id'])->first();

        if ($invoice) {
            return $invoice;
        }

        $reference = (string) ($payment['externalReference'] ?? '');

        return ctype_digit($reference) ? Invoice::query()->with('customer')->find((int) $reference) : null;
    }

    /**
     * @param  list<array{invoice: Invoice, result: SyncResult}>  $results
     * @param  list<array<string, mixed>>  $orphans
     * @param  list<array{0: string, 1: string, 2: string}>  $warnings
     * @param  list<string>  $errors
     */
    private function report(array $results, array $orphans, array $warnings, array $errors, int $read, int $events, bool $dryRun): void
    {
        $changed = array_values(array_filter($results, fn (array $row): bool => $row['result']->changed()));

        $this->newLine();
        $this->info($dryRun ? 'Faturas que MUDARIAM:' : 'Faturas alteradas:');

        if ($changed === []) {
            $this->line('  Nenhuma.');
        } else {
            $this->table(
                ['Fatura', 'Cliente', 'Cobrança', 'Asaas', 'Status', 'Forma', 'Data', 'Valor', 'Detalhes'],
                array_map(fn (array $row): array => $this->changeRow($row['invoice'], $row['result']), $changed),
            );
        }

        $this->newLine();
        $this->info('Totais por tipo:');
        $totals = [];

        foreach (SyncChange::cases() as $change) {
            $count = count(array_filter($results, fn (array $row): bool => $row['result']->change === $change));

            if ($count > 0) {
                $totals[] = [$change->label(), $count];
            }
        }

        $totals[] = ['Cobranças sem fatura no sistema', count($orphans)];
        $totals[] = ['Cobranças de eventos (ignoradas)', $events];
        $totals[] = ['Total de cobranças lidas no Asaas', $read];
        $this->table(['Tipo', 'Qtd.'], $totals);

        if ($orphans !== []) {
            $this->newLine();
            $this->warn('Cobranças do Asaas SEM fatura no sistema (só relatório; nada foi importado):');
            $this->table(
                ['Cobrança', 'Cliente', 'Referência', 'Status', 'Valor', 'Vencimento'],
                array_map(fn (array $payment): array => [
                    $payment['id'],
                    $this->customerName($payment['customer'] ?? null),
                    $payment['externalReference'] ?? '—',
                    $payment['status'] ?? '—',
                    $this->money($payment['value'] ?? 0),
                    isset($payment['dueDate']) ? Carbon::parse($payment['dueDate'])->format('d/m/Y') : '—',
                ], $orphans),
            );
        }

        if ($warnings !== []) {
            $this->newLine();
            $this->warn('Avisos (conferir manualmente):');
            $this->table(['Fatura', 'Cobrança', 'Aviso'], $warnings);
        }

        foreach ($errors as $error) {
            $this->error($error);
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('Simulação: nada foi gravado. Confira o relatório e rode sem --dry-run para aplicar.');
        }
    }

    /** @return list<string> */
    private function changeRow(Invoice $invoice, SyncResult $result): array
    {
        $details = $result->updates;

        if ($result->paymentsReversed > 0) {
            $details[] = "{$result->paymentsReversed} pagamento(s) desfeito(s), recibo cancelado";
        }

        return [
            $this->invoiceLabel($invoice),
            $invoice->customer?->name ?? '—',
            $result->asaasPaymentId,
            $result->asaasStatus ?? '—',
            ($result->statusBefore?->getLabel() ?? '—').' → '.($result->statusAfter?->getLabel() ?? '—'),
            $result->paymentMethod?->getLabel() ?? '—',
            $result->paymentDate?->format('d/m/Y') ?? '—',
            $this->money($result->paymentAmount ?? $invoice->amount),
            implode('; ', $details) ?: '—',
        ];
    }

    private function invoiceLabel(Invoice $invoice): string
    {
        return $invoice->number ?: '#'.$invoice->getKey();
    }

    private function customerName(?string $asaasCustomerId): string
    {
        if (! $asaasCustomerId) {
            return '—';
        }

        if (! array_key_exists($asaasCustomerId, $this->customerNames)) {
            $this->customerNames[$asaasCustomerId] = Customer::query()->where('asaas_customer_id', $asaasCustomerId)->value('name');
        }

        return $this->customerNames[$asaasCustomerId] ?? $asaasCustomerId;
    }

    private function money(float|string|null $value): string
    {
        return Number::currency((float) $value, 'BRL', 'pt_BR');
    }
}
