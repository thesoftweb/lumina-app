<?php

namespace App\Models;

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

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
