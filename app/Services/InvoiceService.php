<?php

namespace App\Services;

use App\DTOs\CreateInvoiceDTO;
use App\Enums\InvoiceType;
use App\Models\Enrollment;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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

        // Calcula desconto
        $discount = $this->calculateDiscount($enrollment, $amount);

        $enrollment->enrollment_tax_paid = true;
        $enrollment->save();

        return $this->createInvoice(
            new CreateInvoiceDTO(
                customer_id: $enrollment->student->customer_id,
                amount: $discount['final_amount'],
                type: InvoiceType::ENROLLMENT,
                enrollment_id: $enrollment->id,
                issue_date: $issueDate,
                due_date: $dueDate,
                company_id: $companyId,
                reference: "MAT-" . $enrollment->id . "-" . now()->format('Ymd'),
                notes: $notes ?? "Taxa de matrícula",
                discountSource: $discount['discount_source'],
                discountType: $discount['discount_type'],
                discountValue: $discount['discount_value'],
                originalAmount: $discount['original_amount'],
                finalAmount: $discount['final_amount'],
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
     *
     * O amount armazenado é sempre o base_amount do plan (valor sem desconto)
     * O desconto é válido até a due_date. Após a due_date, cobra-se o base_amount
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

        // Calcula desconto (baseado no plan e enrollment)
        $discount = $this->calculateTuitionDiscount($enrollment, $amount);

        // Construir descrição dinamicamente
        $description = $this->buildInvoiceDescription($enrollment, $billingPeriodStart);

        $enrollment->tuition_generated = true;
        $enrollment->save();

        return $this->createInvoice(
            new CreateInvoiceDTO(
                customer_id: $enrollment->student->customer_id,
                amount: $discount['base_amount'],  // Sempre o base_amount (valor sem desconto)
                type: InvoiceType::TUITION,
                enrollment_id: $enrollment->id,
                issue_date: $issueDate,
                due_date: $dueDate,
                company_id: $companyId,
                account_id: $accountId,
                billing_period_start: $billingPeriodStart,
                billing_period_end: $billingPeriodEnd,
                reference: "MENS-" . $enrollment->id . "-" . $billingPeriodStart->format('Y-m'),
                notes: $notes ?? $description,
                discountSource: $discount['discount_source'],
                discountType: $discount['discount_type'],
                discountValue: $discount['discount_value'],
                originalAmount: $discount['original_amount'],
                finalAmount: $discount['final_amount'],
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
                reference: "MENS-" . $billingPeriodStart->format('Y-m'),
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
                reference: "SERV-" . now()->format('YmdHis'),
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
                reference: $reference ?? "AVU-" . now()->format('YmdHis'),
                notes: $notes ?? $description ?? "Outra entrada",
            )
        );
    }

    /**
     * Calcula desconto para mensalidades (tuition)
     * Regra: Se não houver desconto customizado, usa o desconto do plano
     * Se houver desconto customizado, usa o desconto da enrollment
     *
     * Armazena sempre:
     * - base_amount: valor base do plan (sem desconto)
     * - final_amount: valor com desconto aplicado
     * - discount_value: percentual ou valor do desconto
     * - discount_type: 'percentage' ou 'fixed'
     * - discount_source: 'plan' ou 'enrollment_custom'
     */
    private function calculateTuitionDiscount(Enrollment $enrollment, float $baseAmount): array
    {
        // Verifica se há desconto personalizado na enrollment
        if ($enrollment->use_custom_discount && $enrollment->discount_value > 0) {
            // Desconto customizado da enrollment
            $discountValue = $enrollment->discount_value;
            $discountType = $enrollment->discount_type; // 'percentage' ou 'fixed'
            $discountSource = 'enrollment_custom';
        } elseif ($enrollment->plan && $enrollment->plan->has_discount && $enrollment->plan->discount_type !== 'none' && $enrollment->plan->discount_value > 0) {
            // Desconto do plano
            $discountValue = $enrollment->plan->discount_value;
            $discountType = $enrollment->plan->discount_type; // 'percentage' ou 'fixed'
            $discountSource = 'plan';
        } else {
            // Sem desconto
            return [
                'base_amount' => $baseAmount,
                'final_amount' => $baseAmount,
                'discount_value' => 0,
                'discount_type' => null,
                'discount_source' => null,
                'original_amount' => $baseAmount,
            ];
        }

        // Calcula o valor final com desconto aplicado
        $finalAmount = $baseAmount;
        if ($discountType === 'percentage') {
            // Desconto em percentual
            $finalAmount = $baseAmount * (1 - ($discountValue / 100));
        } elseif ($discountType === 'fixed') {
            // Desconto em valor fixo
            $finalAmount = $baseAmount - $discountValue;
        }

        $finalAmount = max(0, $finalAmount); // Não pode ser negativo

        return [
            'base_amount' => $baseAmount,           // Valor base do plan (sem desconto)
            'final_amount' => $finalAmount,         // Valor com desconto aplicado
            'discount_value' => $discountValue,     // Percentual ou valor do desconto
            'discount_type' => $discountType,       // 'percentage' ou 'fixed'
            'discount_source' => $discountSource,   // 'plan' ou 'enrollment_custom'
            'original_amount' => $baseAmount,
        ];
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
            'enrollment_id' => $dto->enrollment_id,
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
            'discount_source' => $dto->discountSource ?? 'plan',
            'discount_type' => $dto->discountType,
            'discount_value' => $dto->discountValue,
            'original_amount' => $dto->originalAmount,
            'final_amount' => $dto->finalAmount,
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
            InvoiceType::TUITION => 'MENS',
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

    /**
     * Construir descrição dinâmica da fatura
     * Formato: "Cobrança referente a mensalidade do mês de {mês} do aluno(a) {nome}, da turma {turma}"
     *
     * @param Enrollment $enrollment
     * @param Carbon $billingPeriodStart
     * @return string
     */
    private function buildInvoiceDescription(Enrollment $enrollment, Carbon $billingPeriodStart): string
    {
        try {
            $monthName = $billingPeriodStart->locale('pt_BR')->translatedFormat('F');

            return sprintf(
                'Cobrança referente a mensalidade do mês de %s do aluno(a) %s, da turma %s',
                ucfirst($monthName),
                $enrollment->student->name,
                $enrollment->classroom->name
            );
        } catch (\Exception $e) {
            // Fallback se algo der errado
            return "Mensalidade - {$billingPeriodStart->format('m/Y')}";
        }
    }
}
