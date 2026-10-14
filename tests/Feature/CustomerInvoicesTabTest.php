<?php

namespace Tests\Feature;

use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\RelationManagers\InvoicesRelationManager;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerInvoicesTabTest extends TestCase
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

    private function invoice(string $number, string $status, string $dueDate, float $balance, string $type = 'tuition'): Invoice
    {
        return Invoice::create([
            'customer_id' => $this->customer->id,
            'number' => $number,
            'issue_date' => '2026-10-01',
            'due_date' => $dueDate,
            'amount' => 100,
            'balance' => $balance,
            'status' => $status,
            'billing_type' => $type,
        ]);
    }

    private function table()
    {
        return Livewire::test(InvoicesRelationManager::class, [
            'ownerRecord' => $this->customer,
            'pageClass' => EditCustomer::class,
        ]);
    }

    public function test_edit_page_has_a_faturas_tab(): void
    {
        $page = Livewire::test(EditCustomer::class, ['record' => $this->customer->id])
            ->assertSee('Cadastro')
            ->assertSee('Endereço')
            ->assertSee('Faturas');

        $tabs = $page->instance()->form->getComponents()[0]
            ->getChildSchema()->getComponents();

        $this->assertSame(['Cadastro', 'Endereço', 'Faturas'], array_map(
            fn ($tab): string => $tab->getLabel(),
            $tabs,
        ));
        $this->assertFalse($page->instance()->hasFormWrapper());
    }

    public function test_operational_filters_show_only_this_customers_relevant_invoices(): void
    {
        $late = $this->invoice('ATRASADA', 'open', today()->subDay()->toDateString(), 100);
        $soon = $this->invoice('PROXIMA', 'partial', today()->addDays(3)->toDateString(), 40);
        $paid = $this->invoice('PAGA', 'paid', today()->subDays(2)->toDateString(), 0);
        $canceled = $this->invoice('CANCELADA', 'canceled', today()->subDays(2)->toDateString(), 100);
        $future = $this->invoice('FUTURA', 'open', today()->addDays(10)->toDateString(), 100, 'material');
        $otherCustomer = Customer::create(['name' => 'Outro', 'document' => '987.654.321-00']);
        $otherInvoice = $this->invoice('OUTRO', 'open', today()->subDay()->toDateString(), 100);
        $otherInvoice->update(['customer_id' => $otherCustomer->id]);

        $this->table()->filterTable('past_due')
            ->assertCanSeeTableRecords([$late])
            ->assertCanNotSeeTableRecords([$soon, $paid, $canceled, $future, $otherInvoice]);

        $this->table()->filterTable('due_soon')
            ->assertCanSeeTableRecords([$soon])
            ->assertCanNotSeeTableRecords([$late, $paid, $canceled, $future, $otherInvoice]);

        $this->table()->filterTable('with_balance')
            ->assertCanSeeTableRecords([$late, $soon, $future])
            ->assertCanNotSeeTableRecords([$paid, $canceled, $otherInvoice]);

        $this->table()->filterTable('status', 'paid')
            ->assertCanSeeTableRecords([$paid])
            ->assertCanNotSeeTableRecords([$late]);

        $this->table()->filterTable('billing_type', 'material')
            ->assertCanSeeTableRecords([$future])
            ->assertCanNotSeeTableRecords([$late]);

        $this->table()->filterTable('due_period', [
            'from' => today()->addDays(3)->toDateString(),
            'until' => today()->addDays(3)->toDateString(),
        ])->assertCanSeeTableRecords([$soon])->assertCanNotSeeTableRecords([$late, $future]);
    }

    public function test_new_invoice_action_uses_real_invoice_fields_and_keeps_the_customer_link(): void
    {
        $this->table()->callAction(TestAction::make('create')->table(), [
            'customer_id' => $this->customer->id,
            'billing_type' => 'tuition',
            'issue_date' => today()->toDateString(),
            'due_date' => today()->addDays(10)->toDateString(),
            'amount' => 120,
        ])->assertHasNoFormErrors();

        $this->assertDatabaseHas('invoices', [
            'customer_id' => $this->customer->id,
            'amount' => 120,
            'billing_type' => 'tuition',
        ]);
    }
}
