<?php

namespace App\Services\Asaas;

use App\DTOs\CreateInvoiceDTO;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Importa como fatura (tipo "Outro") uma cobrança criada direto no painel do Asaas, quando o cliente
 * dela é um responsável cadastrado (Customer com aquele asaas_customer_id). Usado pelo webhook e por
 * asaas:reconcile --import. Depois de criar a fatura, aplica a situação da cobrança pelo
 * PaymentSynchronizer (paga, vencida...).
 *
 * Só importa cobrança SEM externalReference: as cobranças geradas pelo sistema levam o id da fatura
 * (ou EVENT-...) e, se a fatura não existe mais, não devem ressuscitá-la.
 */
class ChargeImporter
{
    public function __construct(
        private InvoiceService $invoices,
        private PaymentSynchronizer $synchronizer,
    ) {}

    /**
     * Motivo de a cobrança não poder ser importada, ou null se pode.
     *
     * @param  array<string, mixed>  $payment
     */
    public function reasonNotToImport(array $payment): ?string
    {
        if ($payment['deleted'] ?? false) {
            return 'Cobrança excluída no Asaas.';
        }

        if (($payment['status'] ?? null) === PaymentSynchronizer::REFUNDED_STATUS) {
            return 'Cobrança estornada no Asaas.';
        }

        $reference = trim((string) ($payment['externalReference'] ?? ''));

        if ($reference !== '') {
            return "Cobrança com referência externa ({$reference}) que não corresponde a nenhuma fatura do sistema.";
        }

        if (! isset($payment['value']) || (float) $payment['value'] <= 0 || empty($payment['dueDate'])) {
            return 'Cobrança sem valor ou sem vencimento.';
        }

        if (! $this->customerFor($payment)) {
            return 'Cliente do Asaas ('.($payment['customer'] ?? '—').') não é um responsável cadastrado no sistema.';
        }

        return null;
    }

    /** @param  array<string, mixed>  $payment */
    public function customerFor(array $payment): ?Customer
    {
        $asaasCustomerId = $payment['customer'] ?? null;

        if (! is_string($asaasCustomerId) || $asaasCustomerId === '') {
            return null;
        }

        return Customer::query()->where('asaas_customer_id', $asaasCustomerId)->oldest('id')->first();
    }

    /** @param  array<string, mixed>  $payment */
    public function import(array $payment, bool $dryRun = false): ImportOutcome
    {
        $customer = $this->customerFor($payment);

        if ($reason = $this->reasonNotToImport($payment)) {
            return ImportOutcome::skipped($reason, $customer);
        }

        if ($dryRun) {
            return new ImportOutcome(imported: true, customer: $customer, dryRun: true);
        }

        $asaasId = (string) $payment['id'];

        // PAYMENT_CREATED e PAYMENT_RECEIVED da mesma cobrança chegando juntos não criam duas faturas
        return Cache::lock("asaas:import-charge:{$asaasId}", 60)->block(15, function () use ($payment, $customer, $asaasId): ImportOutcome {
            $existing = Invoice::query()->where('asaas_invoice_id', $asaasId)->first();

            if ($existing) {
                return new ImportOutcome(imported: false, customer: $customer, invoice: $existing, sync: $this->synchronizer->apply($existing, $payment));
            }

            $invoice = DB::transaction(fn (): Invoice => $this->createInvoice($customer, $payment));

            Log::info('Asaas: cobrança criada no painel do Asaas importada como fatura', [
                'invoice_id' => $invoice->id,
                'customer_id' => $customer->id,
                'asaas_payment_id' => $asaasId,
            ]);

            return new ImportOutcome(imported: true, customer: $customer, invoice: $invoice, sync: $this->synchronizer->apply($invoice, $payment));
        });
    }

    /** @param  array<string, mixed>  $payment */
    private function createInvoice(Customer $customer, array $payment): Invoice
    {
        $asaasId = (string) $payment['id'];
        $description = trim((string) ($payment['description'] ?? ''));
        $issueDate = isset($payment['dateCreated']) ? Carbon::parse($payment['dateCreated']) : today();

        $notes = "Importada do Asaas: cobrança {$asaasId}, criada direto no painel do Asaas.";

        if ($description !== '') {
            $notes = $description."\n\n".$notes;
        }

        $invoice = $this->invoices->createInvoice(new CreateInvoiceDTO(
            customer_id: $customer->id,
            amount: round((float) $payment['value'], 2),
            type: InvoiceType::OTHER,
            issue_date: $issueDate,
            due_date: Carbon::parse($payment['dueDate']),
            company_id: $this->companyIdFor($customer),
            reference: $description !== '' ? Str::limit($description, 250, '') : "ASAAS-{$asaasId}",
            notes: $notes,
        ));

        $invoice->update([
            'asaas_invoice_id' => $asaasId,
            'asaas_sync_status' => 'synced',
            'asaas_synced_at' => now(),
            'invoice_link' => $payment['invoiceUrl'] ?? null,
        ]);

        return $invoice;
    }

    /** Escola da fatura: a das faturas anteriores do responsável ou, havendo uma só, a única cadastrada. */
    private function companyIdFor(Customer $customer): ?int
    {
        $companyId = Invoice::query()->where('customer_id', $customer->id)->whereNotNull('company_id')->latest('id')->value('company_id');

        if ($companyId) {
            return (int) $companyId;
        }

        return Company::query()->count() === 1 ? (int) Company::query()->value('id') : null;
    }
}
