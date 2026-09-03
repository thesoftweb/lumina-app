<?php

namespace App\Console\Commands;

use App\Enums\EnrollmentStatus;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Console\Command;

class CancelInvoicesForCanceledEnrollments extends Command
{
    protected $signature = 'invoices:cancel-for-canceled-enrollments
                            {--dry-run : Apenas exibir quantas invoices seriam canceladas}';

    protected $description = 'Cancela invoices não enviadas ao Asaas vinculadas a matrículas canceladas';

    public function handle(): int
    {
        $query = Invoice::query()
            ->whereNull('asaas_invoice_id')
            ->whereNotIn('status', [
                InvoiceStatus::Paid->value,
                InvoiceStatus::Canceled->value,
            ])
            ->whereHas('enrollment', function ($query) {
                $query->where('status', EnrollmentStatus::Canceled->value);
            });

        $count = $query->count();

        if ($this->option('dry-run')) {
            $this->info("Simulação concluída: {$count} invoice(s) seriam canceladas.");

            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->info('Nenhuma invoice elegível foi encontrada.');

            return self::SUCCESS;
        }

        $updated = $query->update([
            'status' => InvoiceStatus::Canceled->value,
            'balance' => 0,
        ]);

        $this->info("Processamento concluído: {$updated} invoice(s) canceladas.");

        return self::SUCCESS;
    }
}
