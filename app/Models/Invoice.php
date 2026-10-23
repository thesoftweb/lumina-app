<?php

namespace App\Models;

use App\Documents\PaymentReceipt;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $guarded = [];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'billing_period_start' => 'date',
        'billing_period_end' => 'date',
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'status' => InvoiceStatus::class,
        'billing_type' => InvoiceType::class,
        'asaas_synced_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Os pagamentos saem do banco em cascata, sem eventos: os recibos são cancelados antes
        static::deleting(function (Invoice $invoice): void {
            $invoice->payments->each(fn (InvoicePayment $payment) => PaymentReceipt::cancelFor(
                $payment,
                'Fatura excluída em '.now()->format('d/m/Y').'.',
            ));
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class);
    }

    /** Fatura com saldo a pagar (aberta, parcial ou vencida); paga, cancelada ou zerada não conta. */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::Partial, InvoiceStatus::Overdue])
            ->where('balance', '>', 0);
    }

    /** Débito vencido: saldo a pagar com vencimento anterior a hoje. */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->unpaid()->whereDate('due_date', '<', today());
    }

    /** Mesmo critério do scope overdue, para uma fatura já carregada. */
    public function isPastDue(): bool
    {
        return (float) $this->balance > 0
            && in_array($this->status, [InvoiceStatus::Open, InvoiceStatus::Partial, InvoiceStatus::Overdue], true)
            && (bool) $this->due_date?->isBefore(today());
    }

    /** Competência legível ("outubro/2026"): mês do período cobrado ou, sem ele, do vencimento. */
    public function competenceLabel(): ?string
    {
        return ($this->billing_period_start ?? $this->due_date)
            ?->copy()->locale('pt_BR')->translatedFormat('F/Y');
    }
}
