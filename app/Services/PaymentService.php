<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Carbon\Carbon;

class PaymentService
{
    /**
     * Registra um pagamento total
     */
    public function payFullInvoice(
        Invoice $invoice,
        string $paymentMethod = 'other',
        ?string $reference = null,
        ?string $notes = null
    ): InvoicePayment {
        $payment = $this->recordPayment(
            invoice: $invoice,
            amount: $invoice->balance,
            paymentMethod: $paymentMethod,
            reference: $reference,
            notes: $notes ?? "Pagamento total"
        );

        $invoice->update([
            'balance' => 0,
            'status' => InvoiceStatus::Paid,
        ]);

        return $payment;
    }

    /**
     * Registra um pagamento parcial
     */
    public function payPartialInvoice(
        Invoice $invoice,
        float $amount,
        string $paymentMethod = 'other',
        ?string $reference = null,
        ?string $notes = null
    ): InvoicePayment {
        if ($amount <= 0) {
            throw new \Exception('Valor de pagamento deve ser maior que zero');
        }

        if ($amount > $invoice->balance) {
            throw new \Exception('Valor do pagamento não pode ser maior que o saldo pendente');
        }

        $payment = $this->recordPayment(
            invoice: $invoice,
            amount: $amount,
            paymentMethod: $paymentMethod,
            reference: $reference,
            notes: $notes ?? "Pagamento parcial"
        );

        $newBalance = $invoice->balance - $amount;

        $status = $newBalance == 0 ? InvoiceStatus::Paid : InvoiceStatus::Partial;

        $invoice->update([
            'balance' => $newBalance,
            'status' => $status,
        ]);

        return $payment;
    }

    /**
     * Registra um pagamento genérico
     */
    private function recordPayment(
        Invoice $invoice,
        float $amount,
        string $paymentMethod = 'other',
        ?string $reference = null,
        ?string $notes = null
    ): InvoicePayment {
        return InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => now()->toDateString(),
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'reference' => $reference,
            'notes' => $notes,
        ]);
    }

    /**
     * Cancela uma fatura
     */
    public function cancelInvoice(Invoice $invoice, ?string $notes = null): void
    {
        $invoice->update([
            'status' => InvoiceStatus::Canceled,
            'balance' => 0,
            'notes' => ($invoice->notes ?? '') . "\n\nCancelada em: " . now()->format('d/m/Y H:i:s') . ($notes ? " - {$notes}" : ''),
        ]);
    }

    /**
     * Revert um pagamento (desfazer)
     */
    public function reversePayment(InvoicePayment $payment): void
    {
        $invoice = $payment->invoice;

        // Restaura o saldo
        $newBalance = $invoice->balance + $payment->amount;

        $status = match (true) {
            $newBalance >= $invoice->amount => InvoiceStatus::Open,
            $newBalance > 0 => InvoiceStatus::Partial,
            default => InvoiceStatus::Open,
        };

        $invoice->update([
            'balance' => $newBalance,
            'status' => $status,
        ]);

        $payment->delete();
    }

    /**
     * Obtém o histórico de pagamentos de uma fatura
     */
    public function getPaymentHistory(Invoice $invoice)
    {
        return $invoice->payments()->orderBy('payment_date', 'desc')->get();
    }

    /**
     * Calcula o total pago de uma fatura
     */
    public function getTotalPaid(Invoice $invoice): float
    {
        return (float) $invoice->payments()->sum('amount');
    }
}
