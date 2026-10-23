<?php

namespace App\Observers;

use App\Enums\EnrollmentStatus;
use App\Enums\InvoiceStatus;
use App\Models\Enrollment;

class EnrollmentObserver
{
    public function updated(Enrollment $enrollment): void
    {
        if (! $enrollment->wasChanged('status') || $enrollment->status !== EnrollmentStatus::Canceled) {
            return;
        }

        $enrollment->invoices()
            ->whereNull('asaas_invoice_id')
            ->whereNotIn('status', [
                InvoiceStatus::Paid->value,
                InvoiceStatus::Canceled->value,
            ])
            ->update([
                'status' => InvoiceStatus::Canceled->value,
                'balance' => 0,
            ]);
    }
}
