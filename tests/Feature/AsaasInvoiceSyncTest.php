<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\RelationManagers\InvoicesRelationManager;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\AsaasService;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class AsaasInvoiceSyncTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);
        $this->customer = Customer::create(['name' => 'Responsável', 'document' => '123.456.789-00']);
    }

    private function invoice(?string $asaasId, ?Customer $customer = null): Invoice
    {
        return Invoice::create([
            'customer_id' => ($customer ?? $this->customer)->id,
            'issue_date' => today()->subDays(5),
            'due_date' => today()->addDays(5),
            'amount' => 100,
            'balance' => 100,
            'status' => InvoiceStatus::Open,
            'billing_type' => 'tuition',
            'asaas_invoice_id' => $asaasId,
        ]);
    }

    private function table()
    {
        return Livewire::test(InvoicesRelationManager::class, [
            'ownerRecord' => $this->customer,
            'pageClass' => EditCustomer::class,
        ]);
    }

    public function test_syncing_a_received_payment_is_idempotent_and_updates_the_balance(): void
    {
        $invoice = $this->invoice('pay_received');
        Http::fake(['*/payments/pay_received' => Http::response(['id' => 'pay_received', 'status' => 'RECEIVED'])]);

        $service = app(AsaasService::class);
        $this->assertTrue($service->syncPaymentStatus($invoice));
        $this->assertTrue($service->syncPaymentStatus($invoice));

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame('0.00', $invoice->fresh()->balance);
        $this->assertNotNull($invoice->fresh()->asaas_synced_at);
        $this->assertSame(1, $invoice->payments()->count());
        $this->assertSame('pay_received', $invoice->payments()->first()->asaas_payment_id);
    }

    public function test_confirmed_is_paid_and_unknown_status_does_not_overwrite_the_invoice(): void
    {
        $confirmed = $this->invoice('pay_confirmed');
        $unknown = $this->invoice('pay_unknown');
        Http::fake([
            '*/payments/pay_confirmed' => Http::response(['id' => 'pay_confirmed', 'status' => 'CONFIRMED']),
            // REFUNDED agora é tratado (estorno); um status que a API venha a criar segue sem efeito
            '*/payments/pay_unknown' => Http::response(['id' => 'pay_unknown', 'status' => 'STATUS_NOVO_DA_API']),
        ]);

        $service = app(AsaasService::class);
        $this->assertTrue($service->syncPaymentStatus($confirmed));
        $this->assertFalse($service->syncPaymentStatus($unknown));
        $this->assertSame(InvoiceStatus::Paid, $confirmed->fresh()->status);
        $this->assertSame(InvoiceStatus::Open, $unknown->fresh()->status);
        $this->assertSame(0, $unknown->payments()->count());
    }

    public function test_bulk_action_only_syncs_linked_invoices_of_the_current_customer(): void
    {
        $linked = $this->invoice('pay_linked');
        $unlinked = $this->invoice(null);
        $other = Customer::create(['name' => 'Outro', 'document' => '987.654.321-00']);
        $otherInvoice = $this->invoice('pay_other', $other);

        $asaas = Mockery::mock(AsaasService::class);
        $asaas->shouldReceive('syncPaymentStatus')->once()
            ->withArgs(fn (Invoice $invoice): bool => $invoice->id === $linked->id)
            ->andReturn(true);
        $this->app->instance(AsaasService::class, $asaas);

        $this->table()
            ->callAction(TestAction::make('syncCustomerInvoices')->table())
            ->assertNotified('Sincronização concluída');

        $this->assertNotNull($unlinked);
        $this->assertNotNull($otherInvoice);
    }

    public function test_single_invoice_action_reports_a_failed_lookup(): void
    {
        $invoice = $this->invoice('pay_missing');
        $asaas = Mockery::mock(AsaasService::class);
        $asaas->shouldReceive('syncPaymentStatus')->once()->andReturn(false);
        $this->app->instance(AsaasService::class, $asaas);

        $this->table()
            ->callAction(TestAction::make('sync_asaas_payment')->table($invoice))
            ->assertNotified('Erro ao sincronizar');
    }

    public function test_single_invoice_action_shows_the_updated_status(): void
    {
        $invoice = $this->invoice('pay_received');
        Http::fake(['*/payments/pay_received' => Http::response(['id' => 'pay_received', 'status' => 'RECEIVED'])]);

        $this->table()
            ->callAction(TestAction::make('sync_asaas_payment')->table($invoice))
            ->assertNotified('Status sincronizado');

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }
}
