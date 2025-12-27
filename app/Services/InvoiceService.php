<?php

namespace App\Services;

use App\DTOs\CreateInvoiceDTO;
use App\Enums\InvoiceType;
use App\Models\Enrollment;
use App\Models\Invoice;
use Carbon\Carbon;


class InvoiceService
{
    /**
     * Gera uma fatura de matrícula baseada em um Enrollment
     */
    public function createEnrollmentInvoiceFromEnrollment(
        Enrollment $enrollment,
        float $amount,
        ?int $companyId = null,
        ?string $notes = null
    ): Invoice {
        $issueDate = now();
        $dueDate = Carbon::parse($enrollment->enrollment_date)->copy()->addDays(7);

        return $this->createInvoice(
            new CreateInvoiceDTO(
                customer_id: $enrollment->student->customer_id,
                amount: $amount,
                type: InvoiceType::ENROLLMENT,
                issue_date: $issueDate,
                due_date: $dueDate,
                company_id: $companyId,
                reference: "MAT-" . $enrollment->id . "-" . now()->format('Ymd'),
                notes: $notes ?? "Taxa de matrícula",
            )
        );
    }

    /**
     * Gera uma fatura de matrícula
     */
    public function createEnrollmentInvoice(
        int $customerId,
        float $amount,
        ?Carbon $issueDateArgument = null,
        ?int $companyId = null,
        ?string $notes = null
    ): Invoice {
        $issueDate = $issueDateArgument ?? now();
        $dueDate = $issueDate->copy()->addDays(7); // Padrão: vencimento em 7 dias

        return $this->createInvoice(
            new CreateInvoiceDTO(
                customer_id: $customerId,
                amount: $amount,
                type: InvoiceType::ENROLLMENT,
                issue_date: $issueDate,
                due_date: $dueDate,
                company_id: $companyId,
                reference: "MAT-" . now()->format('YmdHis'),
                notes: $notes ?? "Taxa de matrícula",
            )
        );
    }

    /**
     * Gera uma fatura de mensalidade baseada em um Enrollment
     * A data de vencimento é fixa no dia_of_payment definido no enrollment
     */
    public function createTuitionInvoiceFromEnrollment(
        Enrollment $enrollment,
        float $amount,
        Carbon $billingPeriodStart,
        Carbon $billingPeriodEnd,
        ?int $companyId = null,
        ?int $accountId = null,
        ?string $notes = null
    ): Invoice {
        $issueDate = now();

        // Usa o day_of_payment do enrollment como dia fixo de vencimento
        $paymentDay = $enrollment->day_of_payment ?? 5; // Default: 5 se não definido
        $dueDate = $billingPeriodStart->copy()->setDay(min($paymentDay, $billingPeriodStart->daysInMonth));

        return $this->createInvoice(
            new CreateInvoiceDTO(
                customer_id: $enrollment->student->customer_id,
                amount: $amount,
                type: InvoiceType::TUITION,
                issue_date: $issueDate,
                due_date: $dueDate,
                company_id: $companyId,
                account_id: $accountId,
                billing_period_start: $billingPeriodStart,
                billing_period_end: $billingPeriodEnd,
                reference: "TUI-" . $enrollment->id . "-" . $billingPeriodStart->format('Y-m'),
                notes: $notes ?? "Mensalidade - {$billingPeriodStart->format('m/Y')}",
            )
        );
    }

    /**
     * Gera uma fatura de mensalidade
     */
    public function createTuitionInvoice(
        int $customerId,
        float $amount,
        Carbon $billingPeriodStart,
        Carbon $billingPeriodEnd,
        ?Carbon $issueDateArgument = null,
        ?int $companyId = null,
        ?int $accountId = null,
        ?string $notes = null
    ): Invoice {
        $issueDate = $issueDateArgument ?? now();
        $dueDate = $issueDate->copy()->addDays(config('invoices.tuition_due_days', 10));

        return $this->createInvoice(
            new CreateInvoiceDTO(
                customer_id: $customerId,
                amount: $amount,
                type: InvoiceType::TUITION,
                issue_date: $issueDate,
                due_date: $dueDate,
                company_id: $companyId,
                account_id: $accountId,
                billing_period_start: $billingPeriodStart,
                billing_period_end: $billingPeriodEnd,
                reference: "TUI-" . $billingPeriodStart->format('Y-m'),
                notes: $notes ?? "Mensalidade - {$billingPeriodStart->format('m/Y')}",
            )
        );
    }

    /**
     * Gera uma fatura de serviço adicional
     */
    public function createServiceInvoice(
        int $customerId,
        float $amount,
        string $description,
        ?Carbon $issueDateArgument = null,
        ?int $companyId = null,
        ?string $notes = null
    ): Invoice {
        $issueDate = $issueDateArgument ?? now();
        $dueDate = $issueDate->copy()->addDays(5);

        return $this->createInvoice(
            new CreateInvoiceDTO(
                customer_id: $customerId,
                amount: $amount,
                type: InvoiceType::SERVICE,
                issue_date: $issueDate,
                due_date: $dueDate,
                company_id: $companyId,
                reference: "SRV-" . now()->format('YmdHis'),
                notes: $notes ?? $description,
            )
        );
    }

    /**
     * Gera uma fatura de material didático
     */
    public function createMaterialInvoice(
        int $customerId,
        float $amount,
        string $description,
        ?Carbon $issueDateArgument = null,
        ?int $companyId = null,
        ?string $notes = null
    ): Invoice {
        $issueDate = $issueDateArgument ?? now();
        $dueDate = $issueDate->copy()->addDays(3);

        return $this->createInvoice(
            new CreateInvoiceDTO(
                customer_id: $customerId,
                amount: $amount,
                type: InvoiceType::MATERIAL,
                issue_date: $issueDate,
                due_date: $dueDate,
                company_id: $companyId,
                reference: "MAT-" . now()->format('YmdHis'),
                notes: $notes ?? $description,
            )
        );
    }

    /**
     * Gera uma fatura genérica/outras entradas
     */
    public function createOtherInvoice(
        int $customerId,
        float $amount,
        ?string $description = null,
        ?Carbon $issueDateArgument = null,
        ?int $companyId = null,
        ?string $reference = null,
        ?string $notes = null
    ): Invoice {
        $issueDate = $issueDateArgument ?? now();
        $dueDate = $issueDate->copy()->addDays(7);

        return $this->createInvoice(
            new CreateInvoiceDTO(
                customer_id: $customerId,
                amount: $amount,
                type: InvoiceType::OTHER,
                issue_date: $issueDate,
                due_date: $dueDate,
                company_id: $companyId,
                reference: $reference ?? "OTH-" . now()->format('YmdHis'),
                notes: $notes ?? $description ?? "Outra entrada",
            )
        );
    }

    /**
     * Cria uma fatura através do DTO
     */
    public function createInvoice(CreateInvoiceDTO $dto): Invoice
    {

        $issueDate = $dto->issue_date ?? now();
        $dueDate = $dto->due_date ?? $issueDate->copy()->addDays(7);

        $invoice = Invoice::create([
            'customer_id' => $dto->customer_id,
            'company_id' => $dto->company_id,
            'account_id' => $dto->account_id,
            'amount' => $dto->amount,
            'balance' => $dto->amount,
            'issue_date' => $issueDate->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'status' => 'open',
            'billing_type' => $dto->type->value,
            'reference' => $dto->reference ?? $this->generateReference($dto->type),
            'number' => $dto->number ?? $this->generateNumber(),
            'billing_period_start' => $dto->billing_period_start?->toDateString(),
            'billing_period_end' => $dto->billing_period_end?->toDateString(),
            'notes' => $dto->notes,
        ]);

        return $invoice;
    }

    /**
     * Gera uma referência baseada no tipo de fatura
     */
    private function generateReference(InvoiceType $type): string
    {
        $prefix = match ($type) {
            InvoiceType::ENROLLMENT => 'MAT',
            InvoiceType::TUITION => 'TUI',
            InvoiceType::SERVICE => 'SRV',
            InvoiceType::MATERIAL => 'MATERIAL',
            InvoiceType::OTHER => 'OTH',
        };

        return "{$prefix}-" . now()->format('YmdHis');
    }

    /**
     * Gera um número sequencial de fatura
     */
    private function generateNumber(): string
    {
        $lastInvoice = Invoice::latest('id')->first();
        $nextNumber = ($lastInvoice ? intval($lastInvoice->number ?? 0) : 0) + 1;

        return str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Atualiza o saldo de uma fatura após pagamento parcial
     */
    public function updateBalance(Invoice $invoice, float $paidAmount): Invoice
    {
        $newBalance = max(0, $invoice->balance - $paidAmount);

        $status = match (true) {
            $newBalance == 0 => 'paid',
            $newBalance < $invoice->amount => 'partial',
            default => 'open',
        };

        $invoice->update([
            'balance' => $newBalance,
            'status' => $status,
        ]);

        return $invoice;
    }

    /**
     * Cancela uma fatura
     */
    public function cancel(Invoice $invoice): Invoice
    {
        $invoice->update([
            'status' => 'canceled',
            'balance' => 0,
        ]);

        return $invoice;
    }
}
