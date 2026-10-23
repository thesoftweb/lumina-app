<?php

namespace App\Services\Asaas;

use App\Enums\EnrollmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\AsaasService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Throwable;

/**
 * Operações de fatura feitas pelo sistema e refletidas na cobrança do Asaas.
 *
 * Regra de ouro: Asaas primeiro. Cada operação trava a fatura (lockForUpdate), confere as regras,
 * chama o Asaas, valida a resposta e só então grava no sistema, tudo na mesma transação. Se o Asaas
 * recusar (ou não responder), lança ChargeOperationException e nada muda aqui.
 *
 * A trava fica de pé durante a chamada ao Asaas de propósito: o webhook que o Asaas dispara logo em
 * seguida (ex.: PAYMENT_RECEIVED depois de receiveInCash) passa pelo PaymentSynchronizer, que trava a
 * mesma fatura; ele espera esta transação terminar e encontra a fatura já atualizada, sem duplicar.
 *
 * Faturas sem cobrança no Asaas (antigas) são operadas só no sistema; a ação "Gerar cobrança no
 * Asaas" (attachCharge) vincula uma cobrança a elas.
 */
class ChargeOperations
{
    /** Formas que a secretaria pode registrar num recebimento; no Asaas é sempre a baixa manual. */
    public const RECEIVABLE_METHODS = [
        PaymentType::CASH,
        PaymentType::PIX,
        PaymentType::BANK_TRANSFER,
        PaymentType::CREDIT_CARD,
        PaymentType::DEBIT_CARD,
    ];

    private const OPEN_CHARGE_STATUSES = ['PENDING', 'OVERDUE', 'AWAITING_RISK_ANALYSIS'];

    public function __construct(
        private AsaasApi $api,
        private AsaasService $asaas,
        private PaymentService $payments,
        private PaymentSynchronizer $synchronizer,
    ) {}

    // ----------------------------------------------------------------------------------------
    // Regras (também decidem quais ações aparecem na tela)
    // ----------------------------------------------------------------------------------------

    /** Cobrança vinculada e não excluída no Asaas. */
    public static function hasActiveCharge(Invoice $invoice): bool
    {
        return filled($invoice->asaas_invoice_id) && $invoice->asaas_sync_status !== 'canceled';
    }

    public static function canReceive(Invoice $invoice): bool
    {
        return in_array($invoice->status, [InvoiceStatus::Open, InvoiceStatus::Overdue, InvoiceStatus::Partial], true)
            && (float) ($invoice->balance ?? $invoice->amount) > 0;
    }

    /** Valor e vencimento só mudam em fatura aberta/vencida sem pagamentos. */
    public static function canEditCharge(?Invoice $invoice): bool
    {
        return $invoice === null || (
            in_array($invoice->status, [InvoiceStatus::Open, InvoiceStatus::Overdue], true)
            && ! $invoice->payments()->exists()
        );
    }

    public static function canCancel(Invoice $invoice): bool
    {
        return ! in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Canceled], true)
            && ! $invoice->payments()->exists();
    }

    public static function canRestore(Invoice $invoice): bool
    {
        return $invoice->status === InvoiceStatus::Canceled
            && ! $invoice->payments()->exists()
            && $invoice->enrollment?->status !== EnrollmentStatus::Canceled;
    }

    /**
     * Pagamentos que podem ser desfeitos. Se a cobrança do Asaas foi baixada (há pagamento ligado a
     * ela), só esse pode ser desfeito primeiro: enquanto a cobrança estiver recebida, os pagamentos
     * parciais não podem voltar para ela.
     *
     * @return Collection<int, InvoicePayment>
     */
    public static function undoablePayments(Invoice $invoice): Collection
    {
        if ($invoice->status === InvoiceStatus::Canceled) {
            return new Collection;
        }

        $payments = $invoice->payments()->orderByDesc('payment_date')->orderByDesc('id')->get();

        if (! self::hasActiveCharge($invoice)) {
            return $payments;
        }

        $linked = $payments->filter(fn (InvoicePayment $payment): bool => self::isLinkedToCharge($invoice, $payment));

        return $linked->isNotEmpty() ? $linked->values() : $payments;
    }

    public static function canUndo(Invoice $invoice): bool
    {
        return self::undoablePayments($invoice)->isNotEmpty();
    }

    /** Pagamento registrado a partir da cobrança (mesmo critério do PaymentSynchronizer). */
    public static function isLinkedToCharge(Invoice $invoice, InvoicePayment $payment): bool
    {
        $chargeId = $invoice->asaas_invoice_id;

        return filled($chargeId) && ($payment->asaas_payment_id === $chargeId || $payment->reference === $chargeId);
    }

    // ----------------------------------------------------------------------------------------
    // Criar
    // ----------------------------------------------------------------------------------------

    /**
     * Cria a fatura e a cobrança dela no Asaas, juntas: se o Asaas recusar, a fatura não é criada.
     *
     * @param  Closure(): Invoice  $create  grava a fatura no banco (roda dentro da transação)
     *
     * @throws ChargeOperationException
     */
    public function createInvoice(Closure $create): Invoice
    {
        $chargeId = null;

        try {
            return DB::transaction(function () use ($create, &$chargeId): Invoice {
                $invoice = $create();
                $invoice->refresh();

                $data = $this->postCharge($invoice);
                $chargeId = $data['id'];
                $this->linkCharge($invoice, $data);

                return $invoice->refresh();
            });
        } catch (Throwable $e) {
            // A cobrança chegou a ser criada, mas a fatura não foi gravada: remove a cobrança órfã
            if ($chargeId !== null) {
                $this->discardOrphanCharge($chargeId, $e);
            }

            throw $e;
        }
    }

    /**
     * Gera a cobrança no Asaas para uma fatura que ainda não tem (faturas antigas).
     *
     * @throws ChargeOperationException
     */
    public function attachCharge(Invoice $invoice): Invoice
    {
        DB::transaction(function () use ($invoice): void {
            $locked = $this->lock($invoice);

            if (filled($locked->asaas_invoice_id)) {
                throw new ChargeOperationException('Esta fatura já tem cobrança no Asaas.');
            }

            if (in_array($locked->status, [InvoiceStatus::Paid, InvoiceStatus::Canceled], true)) {
                throw new ChargeOperationException('Só é possível gerar cobrança para fatura com saldo a pagar.');
            }

            if ($locked->enrollment?->status === EnrollmentStatus::Canceled) {
                throw new ChargeOperationException('Não é possível gerar uma cobrança para uma matrícula cancelada.');
            }

            $this->linkCharge($locked, $this->postCharge($locked));
        });

        return $invoice->refresh();
    }

    // ----------------------------------------------------------------------------------------
    // Editar
    // ----------------------------------------------------------------------------------------

    /**
     * Grava as alterações da fatura. Valor, vencimento e observações (a descrição da cobrança) vão
     * para o Asaas enquanto a fatura está aberta/vencida e sem pagamentos; os demais campos
     * (classificação financeira, referência, tipo...) são só do sistema.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ChargeOperationException
     */
    public function update(Invoice $invoice, array $data): Invoice
    {
        DB::transaction(function () use ($invoice, $data): void {
            $locked = $this->lock($invoice);
            $hasCharge = self::hasActiveCharge($locked);
            $editable = self::canEditCharge($locked);

            if (array_key_exists('customer_id', $data) && (int) $data['customer_id'] !== (int) $locked->customer_id && filled($locked->asaas_invoice_id)) {
                throw new ChargeOperationException('O responsável de uma fatura com cobrança no Asaas não pode ser trocado. Cancele esta fatura e crie outra para o novo responsável.');
            }

            $valueChanged = array_key_exists('amount', $data) && $data['amount'] !== null
                && abs((float) $data['amount'] - (float) $locked->amount) >= 0.01;
            $dueDate = array_key_exists('due_date', $data) && filled($data['due_date']) ? Carbon::parse($data['due_date'])->startOfDay() : null;
            $dueChanged = $dueDate !== null && ! $dueDate->isSameDay($locked->due_date);
            $notesChanged = array_key_exists('notes', $data) && trim((string) $data['notes']) !== trim((string) $locked->notes);

            if (($valueChanged || $dueChanged) && ! $editable) {
                throw new ChargeOperationException('Valor e vencimento só podem ser alterados em fatura aberta ou vencida e sem pagamentos.');
            }

            if ($valueChanged && (float) $data['amount'] <= 0) {
                throw new ChargeOperationException('O valor da fatura deve ser maior que zero.');
            }

            $attributes = collect($data)->except(['amount', 'due_date', 'balance', 'status'])->all();

            if ($valueChanged) {
                $attributes = [...$attributes, ...$this->amountAttributes($locked, round((float) $data['amount'], 2))];
            }

            if ($dueChanged) {
                $attributes['due_date'] = $dueDate->toDateString();
            }

            if ($hasCharge && ($valueChanged || $dueChanged || ($notesChanged && $editable))) {
                $current = $this->currentCharge($locked);
                $preview = (clone $locked)->forceFill($attributes);

                // Vencimento novo no passado vai para o Asaas vencendo hoje; sem mudança, fica o da cobrança
                $chargeDue = $dueChanged
                    ? AsaasService::chargeDueDate($dueDate)->format('Y-m-d')
                    : ($current['dueDate'] ?? AsaasService::chargeDueDate($locked->due_date)->format('Y-m-d'));

                $this->putCharge($locked, $current, [
                    'value' => $valueChanged ? round((float) $data['amount'], 2) : (float) ($current['value'] ?? $locked->amount),
                    'dueDate' => $chargeDue,
                    'description' => self::description($preview),
                ]);

                $attributes['asaas_due_date'] = AsaasService::differentDueDate($preview, ['dueDate' => $chargeDue]);
                $attributes['asaas_sync_status'] = 'synced';
                $attributes['asaas_synced_at'] = now();
            }

            if ($valueChanged || $dueChanged) {
                $attributes['status'] = $this->openStatus($dueDate ?? $locked->due_date);
            }

            $locked->update($attributes);
        });

        return $invoice->refresh();
    }

    // ----------------------------------------------------------------------------------------
    // Receber
    // ----------------------------------------------------------------------------------------

    /**
     * Recebimento feito fora do Asaas (secretaria).
     *
     * Total: baixa manual no Asaas (receiveInCash) e quita a fatura com a forma escolhida; o
     * pagamento leva a referência = id da cobrança, então o webhook PAYMENT_RECEIVED que vem depois
     * não registra outro.
     *
     * Parcial: registra o pagamento e muda o valor da cobrança no Asaas para o saldo restante, que o
     * responsável paga por PIX/boleto (a sincronização registra esse restante quando ele pagar).
     *
     * @throws ChargeOperationException
     */
    public function receive(
        Invoice $invoice,
        float $amount,
        PaymentType $method,
        CarbonInterface $date,
        bool $partial = false,
        ?string $reference = null,
        ?string $notes = null,
        bool $notifyCustomer = false,
    ): InvoicePayment {
        $payment = DB::transaction(function () use ($invoice, $amount, $method, $date, $partial, $reference, $notes, $notifyCustomer): InvoicePayment {
            $locked = $this->lock($invoice);
            $amount = round($amount, 2);
            $balance = round((float) ($locked->balance ?? $locked->amount), 2);

            if (! self::canReceive($locked)) {
                throw new ChargeOperationException('Esta fatura não tem saldo a receber.');
            }

            if ($amount <= 0) {
                throw new ChargeOperationException('O valor recebido deve ser maior que zero.');
            }

            if (Carbon::parse($date)->startOfDay()->gt(today())) {
                throw new ChargeOperationException('A data do pagamento não pode ser futura.');
            }

            return $partial
                ? $this->receivePartial($locked, $amount, $balance, $method, $date, $reference, $notes)
                : $this->receiveFull($locked, $amount, $method, $date, $reference, $notes, $notifyCustomer);
        });

        $invoice->refresh();

        return $payment;
    }

    private function receivePartial(Invoice $invoice, float $amount, float $balance, PaymentType $method, CarbonInterface $date, ?string $reference, ?string $notes): InvoicePayment
    {
        if ($amount >= $balance) {
            throw new ChargeOperationException('O pagamento parcial deve ser menor que o saldo ('.self::money($balance).'). Para quitar, use "Pagamento total".');
        }

        $remaining = round($balance - $amount, 2);

        if (self::hasActiveCharge($invoice)) {
            $current = $this->currentCharge($invoice);
            $payload = [
                'value' => $remaining,
                'dueDate' => $current['dueDate'] ?? $invoice->due_date->format('Y-m-d'),
                'description' => self::description($invoice),
            ];

            // O saldo é o que falta pagar: o desconto da cobrança não vale mais sobre ele
            if ((float) ($current['discount']['value'] ?? 0) > 0 || (float) $invoice->discount_value > 0) {
                $payload['discount'] = ['value' => 0, 'dueDateLimitDays' => 0, 'type' => 'FIXED'];
            }

            $this->putCharge($invoice, $current, $payload);
            $invoice->update(['asaas_sync_status' => 'synced', 'asaas_synced_at' => now()]);
        }

        // Sem referência da cobrança: o restante, quando pago pelo Asaas, entra como outro pagamento
        return $this->payments->payPartialInvoice(
            invoice: $invoice,
            amount: $amount,
            paymentMethod: $method->value,
            reference: $reference,
            notes: $notes ?: 'Pagamento parcial registrado no sistema'.(self::hasActiveCharge($invoice) ? '; saldo de '.self::money($remaining).' segue na cobrança do Asaas' : ''),
            paymentDate: $date,
        );
    }

    private function receiveFull(Invoice $invoice, float $amount, PaymentType $method, CarbonInterface $date, ?string $reference, ?string $notes, bool $notifyCustomer): InvoicePayment
    {
        if (! self::hasActiveCharge($invoice)) {
            return $this->payments->settleInvoice(
                invoice: $invoice,
                amount: $amount,
                paymentMethod: $method->value,
                paymentDate: $date,
                reference: $reference,
                notes: $notes ?: 'Pagamento registrado no sistema',
            );
        }

        $chargeId = (string) $invoice->asaas_invoice_id;
        $response = $this->api->receiveInCash($chargeId, $date->format('Y-m-d'), $amount, $notifyCustomer);
        $this->expectCharge($response, $chargeId);

        if (isset($response['status']) && ! in_array($response['status'], PaymentSynchronizer::PAID_STATUSES, true)) {
            throw ChargeOperationException::unexpected("situação {$response['status']} depois da baixa");
        }

        $notes = collect([
            $notes ?: 'Recebido no sistema e baixado no Asaas (recebimento em dinheiro)',
            $reference ? "Comprovante: {$reference}" : null,
        ])->filter()->implode(' · ');

        // Pagamento já registrado por uma sincronização desta cobrança: só acerta a forma
        $linked = $invoice->payments()
            ->where(fn ($query) => $query->where('asaas_payment_id', $chargeId)->orWhere('reference', $chargeId))
            ->first();

        if ($linked) {
            $linked->update(['payment_method' => $method, 'notes' => $notes]);
            $invoice->update(['balance' => 0, 'status' => InvoiceStatus::Paid, 'asaas_sync_status' => 'synced', 'asaas_synced_at' => now()]);

            return $linked;
        }

        $payment = $this->payments->settleInvoice(
            invoice: $invoice,
            amount: $amount,
            paymentMethod: $method->value,
            paymentDate: $date,
            // Mesma ligação que o PaymentSynchronizer usa: o webhook não registra de novo
            reference: $chargeId,
            notes: $notes,
            attributes: [
                'asaas_payment_id' => $chargeId,
                'asaas_sync_status' => 'synced',
                'asaas_synced_at' => now(),
            ],
        );

        $invoice->update(['asaas_sync_status' => 'synced', 'asaas_synced_at' => now()]);

        return $payment;
    }

    // ----------------------------------------------------------------------------------------
    // Desfazer recebimento
    // ----------------------------------------------------------------------------------------

    /**
     * Desfaz um pagamento. Se ele é a baixa da cobrança, desfaz a baixa no Asaas
     * (undoReceivedInCash); se é um parcial, devolve o valor para a cobrança (PUT). O pagamento é
     * apagado (o recibo é cancelado) e a fatura volta a aberta, vencida ou parcial.
     *
     * @throws ChargeOperationException
     */
    public function undoPayment(Invoice $invoice, InvoicePayment $payment): Invoice
    {
        DB::transaction(function () use ($invoice, $payment): void {
            $locked = $this->lock($invoice);
            $payment = $locked->payments()->whereKey($payment->getKey())->lockForUpdate()->first();

            if (! $payment) {
                throw new ChargeOperationException('Pagamento não encontrado nesta fatura.');
            }

            if (! self::undoablePayments($locked)->contains($payment)) {
                throw new ChargeOperationException(self::hasActiveCharge($locked)
                    ? 'Desfaça antes o recebimento registrado na cobrança do Asaas.'
                    : 'Este pagamento não pode ser desfeito.');
            }

            $hasCharge = self::hasActiveCharge($locked);

            if ($hasCharge && self::isLinkedToCharge($locked, $payment)) {
                $this->undoChargeReceipt($locked);
            } else {
                if ($hasCharge) {
                    // Parcial: a cobrança volta a valer o saldo com este pagamento devolvido
                    $current = $this->currentCharge($locked);
                    $this->putCharge($locked, $current, [
                        'value' => $this->balanceWithout($locked, $payment),
                        'dueDate' => $current['dueDate'] ?? $locked->due_date->format('Y-m-d'),
                        'description' => self::description($locked),
                    ]);
                }

                $this->payments->reversePayment($payment);
            }

            $locked->refresh();
            $locked->update([
                'balance' => $this->balanceWithout($locked),
                'status' => $locked->payments()->exists() ? InvoiceStatus::Partial : $this->openStatus($locked->due_date),
                ...($hasCharge ? ['asaas_sync_status' => 'synced', 'asaas_synced_at' => now()] : []),
            ]);
        });

        return $invoice->refresh();
    }

    /**
     * Desfaz a baixa no Asaas (undoReceivedInCash) e aplica a cobrança devolvida pelo mesmo caminho do
     * webhook PAYMENT_RECEIVED_IN_CASH_UNDONE (PaymentSynchronizer com undoReceipt): o pagamento ligado
     * à cobrança é desfeito e o recibo cancelado. Quando o webhook chegar, não há mais pagamento ligado
     * e nada é desfeito duas vezes.
     */
    private function undoChargeReceipt(Invoice $invoice): void
    {
        $chargeId = (string) $invoice->asaas_invoice_id;
        $charge = $this->api->undoReceivedInCash($chargeId);
        $this->expectCharge($charge, $chargeId);

        $charge['status'] ??= $this->openStatus($invoice->due_date) === InvoiceStatus::Overdue ? 'OVERDUE' : 'PENDING';

        if (! in_array($charge['status'], [...PaymentSynchronizer::PENDING_STATUSES, ...PaymentSynchronizer::OVERDUE_STATUSES], true)) {
            throw ChargeOperationException::unexpected("situação {$charge['status']} depois de desfazer o recebimento");
        }

        $this->synchronizer->apply($invoice, $charge, undoReceipt: true);
    }

    // ----------------------------------------------------------------------------------------
    // Cancelar e restaurar
    // ----------------------------------------------------------------------------------------

    /**
     * Cancela a fatura e exclui a cobrança no Asaas. Fatura com pagamento não é cancelada: o
     * recebimento tem de ser desfeito antes.
     *
     * @throws ChargeOperationException
     */
    public function cancel(Invoice $invoice, string $reason): Invoice
    {
        DB::transaction(function () use ($invoice, $reason): void {
            $locked = $this->lock($invoice);

            if ($locked->status === InvoiceStatus::Canceled) {
                throw new ChargeOperationException('Esta fatura já está cancelada.');
            }

            if ($locked->status === InvoiceStatus::Paid || $locked->payments()->exists()) {
                throw new ChargeOperationException('Esta fatura tem pagamento registrado. Desfaça o recebimento antes de cancelar.');
            }

            $hasCharge = self::hasActiveCharge($locked);

            if ($hasCharge) {
                $chargeId = (string) $locked->asaas_invoice_id;
                $response = $this->api->deletePayment($chargeId);

                if (! ($response['deleted'] ?? false)) {
                    throw ChargeOperationException::unexpected('a cobrança não foi excluída');
                }
            }

            $this->payments->cancelInvoice($locked, trim($reason));

            if ($hasCharge) {
                $locked->update(['asaas_sync_status' => 'canceled', 'asaas_synced_at' => now()]);
            }
        });

        return $invoice->refresh();
    }

    /**
     * Restaura a fatura cancelada (e a cobrança excluída no Asaas): ela volta a ser cobrada.
     *
     * @throws ChargeOperationException
     */
    public function restore(Invoice $invoice): Invoice
    {
        DB::transaction(function () use ($invoice): void {
            $locked = $this->lock($invoice);

            if ($locked->status !== InvoiceStatus::Canceled) {
                throw new ChargeOperationException('Só é possível restaurar uma fatura cancelada.');
            }

            if ($locked->payments()->exists()) {
                throw new ChargeOperationException('Esta fatura tem pagamentos registrados e não pode ser restaurada.');
            }

            if ($locked->enrollment?->status === EnrollmentStatus::Canceled) {
                throw new ChargeOperationException('A matrícula desta fatura está cancelada; a fatura não pode voltar a ser cobrada.');
            }

            if (filled($locked->asaas_invoice_id)) {
                $this->restoreCharge($locked);
            }

            $locked->update([
                'status' => $this->openStatus($locked->due_date),
                'balance' => $locked->amount,
                'notes' => trim(($locked->notes ?? '')."\n\nRestaurada em ".now()->format('d/m/Y H:i').'.'),
                ...(filled($locked->asaas_invoice_id) ? ['asaas_sync_status' => 'synced', 'asaas_synced_at' => now()] : []),
            ]);
        });

        return $invoice->refresh();
    }

    private function restoreCharge(Invoice $invoice): void
    {
        $chargeId = (string) $invoice->asaas_invoice_id;

        if ($invoice->asaas_sync_status !== 'canceled') {
            // Cancelada só no sistema: a cobrança pode estar ativa (nada a restaurar) ou ter outro destino
            $current = $this->currentCharge($invoice);

            if (! ($current['deleted'] ?? false)) {
                $status = $current['status'] ?? 'desconhecida';

                if (! in_array($status, self::OPEN_CHARGE_STATUSES, true)) {
                    throw new ChargeOperationException("A cobrança desta fatura no Asaas está com a situação {$status}; a fatura não pode ser restaurada.");
                }

                return;
            }
        }

        $response = $this->api->restorePayment($chargeId);
        $this->expectCharge($response, $chargeId);

        if ($response['deleted'] ?? false) {
            throw ChargeOperationException::unexpected('a cobrança continua excluída');
        }
    }

    // ----------------------------------------------------------------------------------------
    // Apoio
    // ----------------------------------------------------------------------------------------

    /** Descrição da cobrança: as observações da fatura (ou o tipo), no limite de 500 caracteres do Asaas. */
    public static function description(Invoice $invoice): string
    {
        $text = trim((string) $invoice->notes) ?: ($invoice->billing_type?->getLabel() ?? 'Fatura');

        return Str::limit($text, 500, '');
    }

    private function lock(Invoice $invoice): Invoice
    {
        return Invoice::query()->with('enrollment')->lockForUpdate()->findOrFail($invoice->getKey());
    }

    /**
     * Cria a cobrança (cadastra o responsável no Asaas antes, se preciso).
     *
     * @return array<string, mixed>
     */
    private function postCharge(Invoice $invoice): array
    {
        $this->ensureCustomer($invoice->customer);
        $invoice->load('customer');

        $payload = $this->asaas->buildChargePayload($invoice);
        $payload['description'] = self::description($invoice);

        // Fatura com pagamento já registrado: a cobrança é só do saldo, sem desconto
        if ($invoice->exists && $invoice->payments()->exists()) {
            $payload['value'] = round((float) $invoice->balance, 2);
            unset($payload['discount']);
        }

        $data = $this->api->createPayment($payload);

        if (blank($data['id'] ?? null)) {
            throw ChargeOperationException::unexpected('cobrança sem id');
        }

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    private function linkCharge(Invoice $invoice, array $data): void
    {
        $invoice->update([
            'asaas_invoice_id' => $data['id'],
            'asaas_sync_status' => 'synced',
            'asaas_synced_at' => now(),
            'invoice_link' => $data['invoiceUrl'] ?? null,
            'invoice_qrcode' => $data['pixQrCode']['qrCode'] ?? null,
            'asaas_due_date' => AsaasService::differentDueDate($invoice, $data),
        ]);
    }

    private function ensureCustomer(?Customer $customer): void
    {
        if (! $customer) {
            throw new ChargeOperationException('A fatura não tem responsável financeiro.');
        }

        if (filled($customer->asaas_customer_id)) {
            return;
        }

        $this->asaas->createOrUpdateCustomer($customer);
        $customer->refresh();

        if (blank($customer->asaas_customer_id)) {
            throw ChargeOperationException::refused(
                "não foi possível cadastrar o responsável {$customer->name} no Asaas (".($customer->asaas_sync_error ?: 'motivo não informado').')'
            );
        }
    }

    /** @return array<string, mixed> */
    private function currentCharge(Invoice $invoice): array
    {
        $chargeId = (string) $invoice->asaas_invoice_id;
        $current = $this->api->getPayment($chargeId);
        $this->expectCharge($current, $chargeId);

        return $current;
    }

    /**
     * PUT na cobrança, mantendo a forma de cobrança atual (billingType é obrigatório).
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $changes
     */
    private function putCharge(Invoice $invoice, array $current, array $changes): void
    {
        $chargeId = (string) $invoice->asaas_invoice_id;

        $response = $this->api->updatePayment($chargeId, [
            'billingType' => $current['billingType'] ?? config('asaas.invoice.billing_type', 'PIX'),
            ...$changes,
        ]);

        $this->expectCharge($response, $chargeId);
    }

    /** @param  array<string, mixed>  $response */
    private function expectCharge(array $response, string $chargeId): void
    {
        if (($response['id'] ?? null) !== $chargeId) {
            throw ChargeOperationException::unexpected('a resposta não é da cobrança desta fatura');
        }
    }

    /**
     * Valor novo da fatura em aberto (sem pagamentos): o saldo acompanha; o valor cheio e o valor com
     * desconto também, quando a fatura os tem.
     *
     * @return array<string, mixed>
     */
    private function amountAttributes(Invoice $invoice, float $amount): array
    {
        $attributes = ['amount' => $amount, 'balance' => $amount];

        if ($invoice->original_amount !== null) {
            $attributes['original_amount'] = $amount;
        }

        if ($invoice->final_amount !== null) {
            $discount = (float) $invoice->discount_value;
            $attributes['final_amount'] = round(max(0, match ($invoice->discount_type) {
                'percentage' => $amount * (1 - $discount / 100),
                'fixed' => $amount - $discount,
                default => $amount,
            }), 2);
        }

        return $attributes;
    }

    /** Saldo = valor da fatura menos os pagamentos que ficam (sem o informado). */
    private function balanceWithout(Invoice $invoice, ?InvoicePayment $without = null): float
    {
        $paid = (float) $invoice->payments()
            ->when($without, fn ($query) => $query->whereKeyNot($without->getKey()))
            ->sum('amount');

        return round(max(0, (float) $invoice->amount - $paid), 2);
    }

    private function openStatus(?CarbonInterface $dueDate): InvoiceStatus
    {
        return $dueDate !== null && Carbon::parse($dueDate)->startOfDay()->lt(today())
            ? InvoiceStatus::Overdue
            : InvoiceStatus::Open;
    }

    private function discardOrphanCharge(string $chargeId, Throwable $reason): void
    {
        try {
            $this->api->deletePayment($chargeId);
            Log::warning("Asaas: cobrança {$chargeId} excluída porque a fatura não foi gravada", ['error' => $reason->getMessage()]);
        } catch (Throwable $e) {
            Log::error("Asaas: cobrança {$chargeId} ficou sem fatura no sistema; exclua-a no Asaas", [
                'error' => $reason->getMessage(),
                'delete_error' => $e->getMessage(),
            ]);
        }
    }

    private static function money(float $value): string
    {
        return Number::currency($value, 'BRL', 'pt_BR');
    }
}
