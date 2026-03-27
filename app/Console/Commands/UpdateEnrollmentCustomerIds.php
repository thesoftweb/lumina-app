<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateEnrollmentCustomerIds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'enrollments:sync-customer-ids';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza customer_id em enrollments baseado no student.customer_id';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Sincronizando customer_id nas matrículas...');

        // Update enrollments with null customer_id
        $updated = DB::statement('
            UPDATE enrollments e
            JOIN students s ON e.student_id = s.id
            SET e.customer_id = s.customer_id
            WHERE e.customer_id IS NULL
        ');

        $this->info("✓ Matrículas atualizadas!");

        // Get statistics
        $totalEnrollments = Enrollment::count();
        $enrollmentsWithCustomer = Enrollment::whereNotNull('customer_id')->count();
        $enrollmentsWithoutCustomer = Enrollment::whereNull('customer_id')->count();

        $this->line('');
        $this->info('Estatísticas:');
        $this->line("Total de matrículas: {$totalEnrollments}");
        $this->line("Matrículas com customer_id: {$enrollmentsWithCustomer}");
        $this->line("Matrículas SEM customer_id: {$enrollmentsWithoutCustomer}");

        if ($enrollmentsWithoutCustomer > 0) {
            $this->warn('⚠ Ainda há matrículas sem customer_id!');
        } else {
            $this->info('✓ Todas as matrículas têm customer_id!');
        }

        return 0;
    }
}
