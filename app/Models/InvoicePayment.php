<?php

namespace App\Models;

use App\Documents\PaymentReceipt;
use App\Enums\PaymentType as EnumsPaymentType;
use Illuminate\Database\Eloquent\Model;
use PaymentType;

class InvoicePayment extends Model
{
    protected $table = 'invoice_payments';

    protected $guarded = [];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'payment_method' => EnumsPaymentType::class,
    ];

    protected static function booted(): void
    {
        static::deleting(function (InvoicePayment $payment): void {
            PaymentReceipt::cancelFor($payment, 'Pagamento estornado em '.now()->format('d/m/Y').'.');
        });
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
