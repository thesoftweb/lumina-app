<?php

namespace App\Documents;

use App\Enums\InvoiceType;
use App\Enums\IssuedDocumentType;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\IssuedDocument;
use App\Models\User;
use App\Services\DocumentIssuer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

/**
 * Recibo de um pagamento registrado na fatura. Cada pagamento tem um recibo: pedir de novo devolve o
 * mesmo documento (2ª via); só depois de cancelado é possível emitir outro.
 */
class PaymentReceipt
{
    public static function existing(InvoicePayment $payment): ?IssuedDocument
    {
        return IssuedDocument::query()
            ->where('type', IssuedDocumentType::PaymentReceipt)
            ->whereMorphedTo('documentable', $payment)
            ->whereNull('canceled_at')
            ->latest('id')
            ->first();
    }

    /** Pagamento estornado ou excluído: o recibo deixa de valer, e a verificação pública mostra o motivo. */
    public static function cancelFor(InvoicePayment $payment, string $reason): void
    {
        if ($receipt = self::existing($payment)) {
            app(DocumentIssuer::class)->cancel($receipt, auth()->user(), $reason);
        }
    }

    public static function issue(InvoicePayment $payment, User $issuer): IssuedDocument
    {
        return DB::transaction(function () use ($payment, $issuer): IssuedDocument {
            // Trava o pagamento: dois cliques seguidos não geram dois recibos
            $payment = InvoicePayment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($existing = self::existing($payment)) {
                return $existing;
            }

            $invoice = $payment->invoice()->with(['customer', 'enrollment.student'])->first();

            if (! $invoice?->customer) {
                throw ValidationException::withMessages(['invoice' => 'A fatura deste pagamento não tem responsável financeiro cadastrado.']);
            }

            if ((float) $payment->amount <= 0) {
                throw ValidationException::withMessages(['payment' => 'Só é possível emitir recibo de pagamento com valor maior que zero.']);
            }

            $student = $invoice->enrollment?->student;

            return app(DocumentIssuer::class)->issue(
                type: IssuedDocumentType::PaymentReceipt,
                documentable: $payment,
                subject: $invoice->customer->name,
                content: [
                    'payer' => ['name' => $invoice->customer->name, 'cpf' => $invoice->customer->document],
                    'student' => $student ? ['name' => $student->name, 'gender' => $student->gender] : null,
                    'invoice' => [
                        'number' => $invoice->number ?: (string) $invoice->getKey(),
                        // "à mensalidade de ...", "ao material didático": o artigo vai junto para o texto
                        ...self::describeInvoice($invoice),
                        'amount' => self::money($invoice->amount),
                        'due_date' => $invoice->due_date?->format('d/m/Y'),
                    ],
                    'payment' => [
                        'amount' => self::money($payment->amount),
                        'amount_in_words' => DocumentText::moneyInWords($payment->amount),
                        'date' => $payment->payment_date?->format('d/m/Y'),
                        'method' => $payment->payment_method?->getLabel(),
                        'partial' => (float) $payment->amount < (float) $invoice->amount,
                    ],
                ],
                issuer: $issuer,
            );
        });
    }

    /**
     * @return array{article: string, description: string} ex.: ['à', 'mensalidade de fevereiro de 2026']
     */
    public static function describeInvoice(Invoice $invoice): array
    {
        $period = $invoice->billing_period_start ?? $invoice->due_date;

        [$article, $description] = match ($invoice->billing_type) {
            InvoiceType::TUITION => ['à', 'mensalidade'.($period ? ' de '.$period->locale('pt_BR')->translatedFormat('F \d\e Y') : '')],
            InvoiceType::ENROLLMENT => ['à', 'taxa de matrícula'],
            InvoiceType::MATERIAL => ['ao', 'material didático'],
            InvoiceType::SERVICE => ['ao', 'serviço adicional'],
            InvoiceType::EVENT => ['ao', 'evento ou contribuição'],
            default => ['à', 'fatura'],
        };

        return ['article' => $article, 'description' => $description];
    }

    private static function money(float|string|null $value): string
    {
        return Number::currency((float) $value, 'BRL', 'pt_BR');
    }
}
