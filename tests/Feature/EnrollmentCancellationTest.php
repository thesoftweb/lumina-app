<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Services\AsaasService;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class EnrollmentCancellationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('document')->unique();
            $table->string('asaas_customer_id')->nullable();
            $table->timestamps();
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('classroom_id');
            $table->date('enrollment_date');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('enrollment_id')->nullable();
            $table->date('issue_date');
            $table->date('due_date');
            $table->decimal('amount', 10, 2);
            $table->decimal('balance', 10, 2)->nullable();
            $table->string('status')->default('open');
            $table->string('billing_type')->default('tuition');
            $table->string('asaas_invoice_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_canceling_an_enrollment_cancels_only_unpaid_invoices_not_sent_to_asaas(): void
    {
        $enrollment = $this->createEnrollment();

        $open = $this->createInvoice($enrollment, InvoiceStatus::Open, balance: 100);
        $partial = $this->createInvoice($enrollment, InvoiceStatus::Partial, balance: 40);
        $overdue = $this->createInvoice($enrollment, InvoiceStatus::Overdue, balance: 100);
        $paid = $this->createInvoice($enrollment, InvoiceStatus::Paid, balance: 0);
        $alreadyCanceled = $this->createInvoice($enrollment, InvoiceStatus::Canceled, balance: 0);
        $sentToAsaas = $this->createInvoice(
            $enrollment,
            InvoiceStatus::Open,
            balance: 100,
            asaasInvoiceId: 'pay_existing',
        );

        $enrollment->update(['status' => EnrollmentStatus::Canceled]);

        foreach ([$open, $partial, $overdue] as $invoice) {
            $this->assertSame(InvoiceStatus::Canceled, $invoice->fresh()->status);
            $this->assertSame('0.00', $invoice->fresh()->balance);
        }

        $this->assertSame(InvoiceStatus::Paid, $paid->fresh()->status);
        $this->assertSame(InvoiceStatus::Canceled, $alreadyCanceled->fresh()->status);
        $this->assertSame(InvoiceStatus::Open, $sentToAsaas->fresh()->status);
        $this->assertSame('100.00', $sentToAsaas->fresh()->balance);
    }

    public function test_reactivating_an_enrollment_does_not_reopen_canceled_invoices(): void
    {
        $enrollment = $this->createEnrollment();
        $invoice = $this->createInvoice($enrollment, InvoiceStatus::Open, balance: 100);

        $enrollment->update(['status' => EnrollmentStatus::Canceled]);
        $enrollment->update(['status' => EnrollmentStatus::Active]);

        $this->assertSame(InvoiceStatus::Canceled, $invoice->fresh()->status);
        $this->assertSame('0.00', $invoice->fresh()->balance);
    }

    public function test_invoice_service_rejects_billing_for_a_canceled_enrollment(): void
    {
        $enrollment = $this->createEnrollment(EnrollmentStatus::Canceled);
        $service = app(InvoiceService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Não é possível gerar faturas para uma matrícula cancelada.');

        $service->createTuitionInvoiceFromEnrollment(
            $enrollment,
            100,
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
        );
    }

    public function test_asaas_command_skips_invoices_linked_to_canceled_enrollments(): void
    {
        config()->set('asaas.api_key', 'test-key');

        $customer = Customer::create([
            'name' => 'Responsável',
            'document' => '12345678901',
            'asaas_customer_id' => 'cus_test',
        ]);

        $activeEnrollment = $this->createEnrollment(EnrollmentStatus::Active);
        $canceledEnrollment = $this->createEnrollment(EnrollmentStatus::Canceled);

        $this->createInvoice($activeEnrollment, InvoiceStatus::Open, 100, customerId: $customer->id);
        $this->createInvoice($canceledEnrollment, InvoiceStatus::Open, 100, customerId: $customer->id);
        $this->createInvoice(null, InvoiceStatus::Open, 100, customerId: $customer->id);

        $asaas = Mockery::mock(AsaasService::class);
        $asaas->shouldReceive('createCharge')
            ->twice()
            ->andReturn(['id' => 'pay_test']);
        $this->app->instance(AsaasService::class, $asaas);

        $this->artisan('asaas:generate-charges', ['--limit' => 100])
            ->assertSuccessful();
    }

    public function test_backfill_command_cancels_only_eligible_invoices_and_supports_dry_run(): void
    {
        $canceledEnrollment = $this->createEnrollment(EnrollmentStatus::Canceled);

        $eligible = $this->createInvoice($canceledEnrollment, InvoiceStatus::Open, 100);
        $paid = $this->createInvoice($canceledEnrollment, InvoiceStatus::Paid, 0);
        $sentToAsaas = $this->createInvoice(
            $canceledEnrollment,
            InvoiceStatus::Open,
            100,
            asaasInvoiceId: 'pay_existing',
        );

        $this->artisan('invoices:cancel-for-canceled-enrollments', ['--dry-run' => true])
            ->expectsOutput('Simulação concluída: 1 invoice(s) seriam canceladas.')
            ->assertSuccessful();

        $this->assertSame(InvoiceStatus::Open, $eligible->fresh()->status);

        $this->artisan('invoices:cancel-for-canceled-enrollments')
            ->expectsOutput('Processamento concluído: 1 invoice(s) canceladas.')
            ->assertSuccessful();

        $this->assertSame(InvoiceStatus::Canceled, $eligible->fresh()->status);
        $this->assertSame('0.00', $eligible->fresh()->balance);
        $this->assertSame(InvoiceStatus::Paid, $paid->fresh()->status);
        $this->assertSame(InvoiceStatus::Open, $sentToAsaas->fresh()->status);

        $this->artisan('invoices:cancel-for-canceled-enrollments')
            ->expectsOutput('Nenhuma invoice elegível foi encontrada.')
            ->assertSuccessful();
    }

    private function createEnrollment(EnrollmentStatus $status = EnrollmentStatus::Active): Enrollment
    {
        return Enrollment::create([
            'student_id' => 1,
            'classroom_id' => 1,
            'enrollment_date' => now()->toDateString(),
            'status' => $status,
        ]);
    }

    private function createInvoice(
        ?Enrollment $enrollment,
        InvoiceStatus $status,
        float $balance,
        ?string $asaasInvoiceId = null,
        int $customerId = 1,
    ): Invoice {
        return Invoice::create([
            'company_id' => 1,
            'customer_id' => $customerId,
            'enrollment_id' => $enrollment?->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'amount' => 100,
            'balance' => $balance,
            'status' => $status,
            'billing_type' => 'tuition',
            'asaas_invoice_id' => $asaasInvoiceId,
        ]);
    }
}
