<?php

namespace App\Services\Asaas;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentType;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\PaymentService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;

/**
 * Aplica numa fatura local a situação de uma cobrança do Asaas (o JSON de GET /v3/payments).
 *
 * Único lugar que traduz os status do Asaas para o sistema: usado pela sincronização de uma fatura,
 * pelo comando antigo asaas:sync-payments e pela conciliação (asaas:reconcile). É idempotente:
 * aplicar a mesma cobrança de novo não registra outro pagamento, e fatura já paga não é alterada.
 */
class PaymentSynchronizer
{
    /** Dinheiro recebido: pelo Asaas, em dinheiro (baixa manual) ou pela negativação. */
    public const PAID_STATUSES = ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH', 'DUNNING_RECEIVED'];

    public const PENDING_STATUSES = ['PENDING', 'AWAITING_RISK_ANALYSIS'];

    public const OVERDUE_STATUSES = ['OVERDUE', 'DUNNING_REQUESTED'];

    public const REFUNDED_STATUS = 'REFUNDED';

    /** Estorno ou contestação ainda em curso: nada muda até o desfecho, só fica o aviso. */
    public const DISPUTE_STATUSES = [
        'REFUND_REQUESTED' => 'Estorno solicitado no Asaas',
        'REFUND_IN_PROGRESS' => 'Estorno em andamento no Asaas',
        'CHARGEBACK_REQUESTED' => 'Chargeback (contestação do cartão) recebido no Asaas',
        'CHARGEBACK_DISPUTE' => 'Chargeback em disputa no Asaas',
        'AWAITING_CHARGEBACK_REVERSAL' => 'Disputa de chargeback ganha, aguardando repasse da adquirente',
    ];

    public function __construct(private PaymentService $payments) {}

    /**
     * @param  array<string, mixed>  $asaasPayment  cobrança como devolvida pela API do Asaas
     * @param  bool  $dryRun  só calcula o que mudaria, sem gravar nada
     * @param  bool  $undoReceipt  o recebimento da cobrança foi desfeito no Asaas (PAYMENT_RECEIVED_IN_CASH_UNDONE):
     *                             com a cobrança de novo a receber, o pagamento local que veio dela é desfeito
     */
    public function apply(Invoice $invoice, array $asaasPayment, bool $dryRun = false, bool $undoReceipt = false): SyncResult
    {
        if ($dryRun) {
            $current = Invoice::query()->find($invoice->getKey()) ?? $invoice;

            return $this->toResult($current, $this->plan($current, $asaasPayment, $undoReceipt), $asaasPayment, true);
        }

        $result = DB::transaction(function () use ($invoice, $asaasPayment, $undoReceipt): SyncResult {
            // Trava a fatura: webhook e conciliação rodando juntos não registram dois pagamentos
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());
            $plan = $this->plan($locked, $asaasPayment, $undoReceipt);
            $result = $this->toResult($locked, $plan, $asaasPayment, false);
            $this->execute($locked, $plan, $asaasPayment);

            return $result;
        });

        $invoice->refresh();
        $this->log($result);

        return $result;
    }

    /**
     * Forma de pagamento real do recebimento: baixa em dinheiro é dinheiro; o resto vem do billingType.
     *
     * @param  array<string, mixed>  $asaasPayment
     */
    public static function paymentMethodFor(array $asaasPayment): PaymentType
    {
        if (($asaasPayment['status'] ?? null) === 'RECEIVED_IN_CASH') {
            return PaymentType::CASH;
        }

        return match ($asaasPayment['billingType'] ?? null) {
            'PIX' => PaymentType::PIX,
            'BOLETO' => PaymentType::BOLETO,
            'CREDIT_CARD' => PaymentType::CREDIT_CARD,
            'DEBIT_CARD' => PaymentType::DEBIT_CARD,
            'TRANSFER', 'DEPOSIT' => PaymentType::BANK_TRANSFER,
            default => PaymentType::ONLINE_PAYMENT,
        };
    }

    /**
     * Decide o que fazer com a fatura, sem gravar nada.
     *
     * @param  array<string, mixed>  $asaasPayment
     * @return array{change: SyncChange, status: ?InvoiceStatus, payment: ?array{amount: float, method: PaymentType, date: CarbonInterface}, reverse: Collection<int, InvoicePayment>, attributes: array<string, mixed>, note: ?string, syncStatus: ?string, updates: list<string>, warnings: list<string>}
     */
    private function plan(Invoice $invoice, array $asaasPayment, bool $undoReceipt = false): array
    {
        $status = $asaasPayment['status'] ?? null;
        $before = $invoice->status;
        $linked = $this->linkedPayments($invoice, (string) $asaasPayment['id']);
        $hasPayments = $invoice->payments()->exists();

        $plan = [
            'change' => SyncChange::Unchanged,
            'status' => $before,
            'payment' => null,
            'reverse' => new Collection,
            'attributes' => [],
            'note' => null,
            'syncStatus' => null,
            'updates' => [],
            'warnings' => [],
        ];

        $attention = function (string $warning) use (&$plan): array {
            $plan['change'] = SyncChange::Attention;
            $plan['warnings'][] = $warning;

            return $plan;
        };

        // Cobrança excluída no Asaas: a fatura deixa de ser cobrada
        if ($asaasPayment['deleted'] ?? false) {
            if ($before === InvoiceStatus::Canceled) {
                return $plan;
            }

            if ($hasPayments || in_array($before, [InvoiceStatus::Paid, InvoiceStatus::Partial], true)) {
                return $attention('Cobrança excluída no Asaas, mas a fatura tem pagamento registrado no sistema; não foi cancelada.');
            }

            return [
                ...$plan,
                'change' => SyncChange::Canceled,
                'status' => InvoiceStatus::Canceled,
                'attributes' => ['balance' => 0],
                'note' => 'Cobrança excluída no Asaas em '.today()->format('d/m/Y').'.',
                'syncStatus' => 'canceled',
            ];
        }

        $unpaid = in_array($status, self::PENDING_STATUSES, true) || in_array($status, self::OVERDUE_STATUSES, true);

        // Baixa (em dinheiro) desfeita no Asaas: desfaz o pagamento que veio desta cobrança
        if ($undoReceipt && $unpaid && $linked->isNotEmpty()) {
            return $this->planUndoReceipt($invoice, $asaasPayment, $plan, $linked);
        }

        if (in_array($status, self::PAID_STATUSES, true)) {
            if ($before === InvoiceStatus::Paid) {
                return $plan;
            }

            if ($before === InvoiceStatus::Canceled) {
                return $attention("Cobrança recebida no Asaas ({$status}), mas a fatura está cancelada no sistema.");
            }

            $plan['change'] = SyncChange::Paid;
            $plan['status'] = InvoiceStatus::Paid;
            $plan['attributes'] = ['balance' => 0];
            $plan['syncStatus'] = 'synced';

            // Pagamento desta cobrança já registrado (ex.: sincronização interrompida): só acerta o status
            if ($linked->isNotEmpty()) {
                return $plan;
            }

            $balance = (float) ($invoice->balance ?? $invoice->amount);
            $amount = isset($asaasPayment['value']) ? (float) $asaasPayment['value'] : $balance;
            $date = $this->paymentDate($asaasPayment);

            if ($date === null) {
                $date = today();
                $plan['warnings'][] = 'O Asaas não informou a data do pagamento; foi usada a data de hoje.';
            }

            if (abs($amount - $balance) >= 0.01) {
                $plan['warnings'][] = 'Valor recebido no Asaas ('.$this->money($amount).') difere do saldo da fatura ('.$this->money($balance).').';
            }

            $plan['payment'] = ['amount' => $amount, 'method' => self::paymentMethodFor($asaasPayment), 'date' => $date];

            return $plan;
        }

        if ($status === self::REFUNDED_STATUS) {
            if ($before === InvoiceStatus::Canceled) {
                return $plan;
            }

            // Pagamentos lançados à mão (não vieram desta cobrança) não são desfeitos automaticamente
            if ($invoice->payments()->whereNotIn('id', $linked->modelKeys())->exists()) {
                return $attention('Cobrança estornada no Asaas, mas a fatura tem pagamentos que não vieram desta cobrança; confira e ajuste manualmente.');
            }

            $refundDate = $this->date($asaasPayment['refunds'][0]['dateCreated'] ?? null) ?? today();

            return [
                ...$plan,
                'change' => SyncChange::Refunded,
                'status' => InvoiceStatus::Canceled,
                'reverse' => $linked,
                'attributes' => ['balance' => 0],
                'note' => 'Cobrança estornada no Asaas em '.$refundDate->format('d/m/Y').'.',
                'syncStatus' => 'synced',
            ];
        }

        if (isset(self::DISPUTE_STATUSES[$status])) {
            return $attention(self::DISPUTE_STATUSES[$status].'; a fatura não foi alterada.');
        }

        if ($unpaid) {
            return $this->planUnpaid($invoice, $asaasPayment, $plan, $hasPayments);
        }

        $plan['change'] = SyncChange::Unknown;
        $plan['warnings'][] = "Status do Asaas não reconhecido: {$status}; a fatura não foi alterada.";

        return $plan;
    }

    /**
     * Cobrança ainda a receber (pendente ou vencida).
     *
     * @param  array<string, mixed>  $asaasPayment
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private function planUnpaid(Invoice $invoice, array $asaasPayment, array $plan, bool $hasPayments): array
    {
        $status = $asaasPayment['status'];
        $before = $invoice->status;

        if ($before === InvoiceStatus::Paid) {
            $plan['change'] = SyncChange::Attention;
            $plan['warnings'][] = "Fatura paga no sistema, mas a cobrança segue em aberto no Asaas ({$status}); dê baixa no Asaas (recebimento em dinheiro) para o cliente não ser cobrado.";

            return $plan;
        }

        // Cancelada por exclusão no Asaas e depois restaurada lá: volta a ser cobrada
        $restored = $before === InvoiceStatus::Canceled && $invoice->asaas_sync_status === 'canceled' && ! $hasPayments;

        if ($before === InvoiceStatus::Canceled && ! $restored) {
            $plan['change'] = SyncChange::Attention;
            $plan['warnings'][] = "Fatura cancelada no sistema, mas a cobrança segue ativa no Asaas ({$status}); cancele a cobrança no Asaas.";

            return $plan;
        }

        $dueDate = $invoice->due_date;

        if (in_array($before, [InvoiceStatus::Open, InvoiceStatus::Overdue], true) && ! $hasPayments) {
            [$attributes, $updates, $warnings] = $this->valueAndDueDateChanges($invoice, $asaasPayment);
            $plan['attributes'] = $attributes;
            $plan['updates'] = $updates;
            $plan['warnings'] = $warnings;
            $dueDate = $attributes['due_date'] ?? $dueDate;
        }

        if ($restored) {
            $plan['attributes']['balance'] = $plan['attributes']['amount'] ?? $invoice->amount;
        }

        // Parcial continua parcial: o pagamento local é parte do saldo
        $target = match (true) {
            $before === InvoiceStatus::Partial => InvoiceStatus::Partial,
            in_array($status, self::OVERDUE_STATUSES, true) => InvoiceStatus::Overdue,
            $dueDate !== null && Carbon::parse($dueDate)->lt(today()) => InvoiceStatus::Overdue,
            default => InvoiceStatus::Open,
        };

        $plan['status'] = $target;

        if ($target !== $before) {
            $plan['change'] = $target === InvoiceStatus::Overdue ? SyncChange::Overdue : SyncChange::Opened;
        } elseif ($plan['updates'] !== []) {
            $plan['change'] = SyncChange::Updated;
        } elseif ($plan['warnings'] !== []) {
            $plan['change'] = SyncChange::Attention;
        }

        if ($plan['change'] !== SyncChange::Unchanged && $plan['change'] !== SyncChange::Attention) {
            $plan['syncStatus'] = 'synced';
        }

        return $plan;
    }

    /**
     * Recebimento desfeito no Asaas: os pagamentos locais desta cobrança são desfeitos (o recibo é
     * cancelado) e a fatura volta a ser cobrada (parcial, se ainda houver outros pagamentos).
     *
     * @param  array<string, mixed>  $asaasPayment
     * @param  array<string, mixed>  $plan
     * @param  Collection<int, InvoicePayment>  $linked
     * @return array<string, mixed>
     */
    private function planUndoReceipt(Invoice $invoice, array $asaasPayment, array $plan, Collection $linked): array
    {
        $hasOtherPayments = $invoice->payments()->whereNotIn('id', $linked->modelKeys())->exists();
        $dueDate = $this->date($asaasPayment['dueDate'] ?? null) ?? $invoice->due_date;

        $target = match (true) {
            $hasOtherPayments => InvoiceStatus::Partial,
            in_array($asaasPayment['status'], self::OVERDUE_STATUSES, true) => InvoiceStatus::Overdue,
            $dueDate !== null && Carbon::parse($dueDate)->lt(today()) => InvoiceStatus::Overdue,
            default => InvoiceStatus::Open,
        };

        return [
            ...$plan,
            'change' => SyncChange::ReceiptUndone,
            'status' => $target,
            'reverse' => $linked,
            'note' => 'Recebimento desfeito no Asaas em '.today()->format('d/m/Y').'; pagamento desfeito e fatura reaberta.',
            'syncStatus' => 'synced',
        ];
    }

    /**
     * Valor e vencimento alterados no Asaas são trazidos para a fatura em aberto (sem pagamentos).
     *
     * @param  array<string, mixed>  $asaasPayment
     * @return array{0: array<string, mixed>, 1: list<string>, 2: list<string>}
     */
    private function valueAndDueDateChanges(Invoice $invoice, array $asaasPayment): array
    {
        $attributes = [];
        $updates = [];
        $warnings = [];

        if (isset($asaasPayment['value'])) {
            $asaasValue = round((float) $asaasPayment['value'], 2);
            // A cobrança vai com o valor cheio (original_amount) e o desconto à parte
            $charged = (float) ($invoice->original_amount ?? $invoice->amount);
            $hasDiscount = $invoice->original_amount !== null && abs((float) $invoice->original_amount - (float) $invoice->amount) >= 0.01;

            if (abs($asaasValue - $charged) >= 0.01) {
                if ($hasDiscount) {
                    $warnings[] = 'Valor da cobrança no Asaas ('.$this->money($asaasValue).') difere do valor cheio da fatura ('.$this->money($charged).'); a fatura tem desconto e não foi atualizada.';
                } else {
                    $attributes['amount'] = $asaasValue;
                    $attributes['balance'] = $asaasValue;
                    if ($invoice->original_amount !== null) {
                        $attributes['original_amount'] = $asaasValue;
                    }
                    if ($invoice->final_amount !== null) {
                        $attributes['final_amount'] = $asaasValue;
                    }
                    $updates[] = 'valor: '.$this->money($invoice->amount).' → '.$this->money($asaasValue);
                }
            }
        }

        $asaasDueDate = $this->date($asaasPayment['dueDate'] ?? null);

        if ($asaasDueDate && ! $asaasDueDate->isSameDay($invoice->due_date)) {
            $attributes['due_date'] = $asaasDueDate->toDateString();
            $updates[] = 'vencimento: '.$invoice->due_date?->format('d/m/Y').' → '.$asaasDueDate->format('d/m/Y');
        }

        return [$attributes, $updates, $warnings];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $asaasPayment
     */
    private function execute(Invoice $invoice, array $plan, array $asaasPayment): void
    {
        $asaasId = (string) $asaasPayment['id'];

        foreach ($plan['reverse'] as $payment) {
            // Devolve o saldo e apaga o pagamento; o recibo emitido é cancelado no deleting
            $this->payments->reversePayment($payment);
        }

        if ($plan['reverse']->isNotEmpty()) {
            $invoice->refresh();
        }

        if ($plan['payment']) {
            $this->payments->settleInvoice(
                invoice: $invoice,
                amount: $plan['payment']['amount'],
                paymentMethod: $plan['payment']['method']->value,
                paymentDate: $plan['payment']['date'],
                reference: $asaasId,
                notes: "Pagamento sincronizado do Asaas ({$asaasPayment['status']})",
                attributes: [
                    'asaas_payment_id' => $asaasId,
                    'asaas_sync_status' => 'synced',
                    'asaas_synced_at' => now(),
                ],
            );
        }

        $updates = [...$plan['attributes'], 'asaas_synced_at' => now()];

        if ($plan['status'] !== $invoice->status) {
            $updates['status'] = $plan['status'];
        }

        if ($plan['syncStatus']) {
            $updates['asaas_sync_status'] = $plan['syncStatus'];
        }

        if ($plan['note']) {
            $updates['notes'] = trim(($invoice->notes ?? '')."\n\n".$plan['note']);
        }

        // Fatura achada pelo externalReference: passa a apontar para a cobrança
        if (! $invoice->asaas_invoice_id) {
            $updates['asaas_invoice_id'] = $asaasId;
        }

        $invoice->update($updates);
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $asaasPayment
     */
    private function toResult(Invoice $invoice, array $plan, array $asaasPayment, bool $dryRun): SyncResult
    {
        return new SyncResult(
            invoiceId: $invoice->getKey(),
            asaasPaymentId: (string) $asaasPayment['id'],
            asaasStatus: ($asaasPayment['deleted'] ?? false) ? 'DELETED' : ($asaasPayment['status'] ?? null),
            change: $plan['change'],
            statusBefore: $invoice->status,
            statusAfter: $plan['status'],
            dryRun: $dryRun,
            paymentCreated: $plan['payment'] !== null,
            paymentMethod: $plan['payment']['method'] ?? null,
            paymentDate: $plan['payment']['date'] ?? null,
            paymentAmount: $plan['payment']['amount'] ?? null,
            paymentsReversed: $plan['reverse']->count(),
            updates: $plan['updates'],
            warnings: $plan['warnings'],
        );
    }

    /** Pagamentos já registrados a partir desta cobrança (pelo id do Asaas ou pela referência). */
    private function linkedPayments(Invoice $invoice, string $asaasId): Collection
    {
        return $invoice->payments()
            ->where(fn ($query) => $query->where('asaas_payment_id', $asaasId)->orWhere('reference', $asaasId))
            ->get();
    }

    /** @param  array<string, mixed>  $asaasPayment */
    private function paymentDate(array $asaasPayment): ?CarbonInterface
    {
        return $this->date($asaasPayment['paymentDate'] ?? null)
            ?? $this->date($asaasPayment['clientPaymentDate'] ?? null)
            ?? $this->date($asaasPayment['confirmedDate'] ?? null);
    }

    private function date(mixed $value): ?CarbonInterface
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function money(float|string|null $value): string
    {
        return Number::currency((float) $value, 'BRL', 'pt_BR');
    }

    private function log(SyncResult $result): void
    {
        $context = [
            'invoice_id' => $result->invoiceId,
            'asaas_payment_id' => $result->asaasPaymentId,
            'asaas_status' => $result->asaasStatus,
            'change' => $result->change->value,
            'status_before' => $result->statusBefore?->value,
            'status_after' => $result->statusAfter?->value,
            'updates' => $result->updates,
            'warnings' => $result->warnings,
        ];

        if ($result->needsAttention() || ! $result->recognized()) {
            Log::warning('Asaas: cobrança sincronizada com avisos', $context);
        } elseif ($result->changed()) {
            Log::info('Asaas: fatura atualizada pela cobrança', $context);
        }
    }
}
