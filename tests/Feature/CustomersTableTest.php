<?php

namespace Tests\Feature;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomersTableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);
    }

    private function customer(string $document, array $data = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Responsável '.$document, 'document' => $document,
            'email' => $document.'@example.test', 'phone' => '11999999999',
        ], $data));
    }

    public function test_rows_open_edit_and_only_edit_is_shown(): void
    {
        $customer = $this->customer('11111111111');
        $page = Livewire::test(ListCustomers::class)
            ->assertCanSeeTableRecords([$customer])
            ->assertTableActionExists('edit')
            ->assertTableActionDoesNotExist('view');

        $this->assertSame(CustomerResource::getUrl('edit', ['record' => $customer]), $page->instance()->getTable()->getRecordUrl($customer));
        $this->assertArrayNotHasKey('view', CustomerResource::getPages());
        $page->searchTable($customer->email)->assertCanSeeTableRecords([$customer]);
    }

    public function test_filters_find_students_missing_contact_sync_and_registration_period(): void
    {
        $linked = $this->customer('11111111111', ['email' => null, 'asaas_sync_status' => 'failed']);
        $other = $this->customer('22222222222', ['asaas_sync_status' => 'synced']);
        $linked->forceFill(['created_at' => '2026-01-15 12:00:00'])->save();
        $other->forceFill(['created_at' => '2026-02-15 12:00:00'])->save();
        Student::create(['name' => 'Aluno', 'customer_id' => $linked->id, 'date_of_birth' => '2015-01-01', 'gender' => 'M']);

        Livewire::test(ListCustomers::class)->filterTable('has_students', true)
            ->assertCanSeeTableRecords([$linked])->assertCanNotSeeTableRecords([$other]);
        Livewire::test(ListCustomers::class)->filterTable('has_students', false)
            ->assertCanSeeTableRecords([$other])->assertCanNotSeeTableRecords([$linked]);
        Livewire::test(ListCustomers::class)->filterTable('missing_contact')
            ->assertCanSeeTableRecords([$linked])->assertCanNotSeeTableRecords([$other]);
        Livewire::test(ListCustomers::class)->filterTable('asaas_sync_status', 'failed')
            ->assertCanSeeTableRecords([$linked])->assertCanNotSeeTableRecords([$other]);
        Livewire::test(ListCustomers::class)->filterTable('registered', ['from' => '2026-01-15', 'until' => '2026-01-15'])
            ->assertCanSeeTableRecords([$linked])->assertCanNotSeeTableRecords([$other]);
    }

    public function test_overdue_filter_excludes_paid_canceled_zero_balance_and_due_today(): void
    {
        $late = $this->customer('11111111111');
        $others = collect();
        foreach ([['open', 50, -1], ['partial', 20, -1], ['paid', 0, -1], ['canceled', 50, -1], ['open', 0, -1], ['open', 50, 0]] as $i => [$status, $balance, $days]) {
            $customer = $i < 2 ? $late : $this->customer('2222222222'.$i);
            if ($i >= 2) {
                $others->push($customer);
            }
            Invoice::create([
                'customer_id' => $customer->id, 'issue_date' => today()->subDays(10),
                'due_date' => today()->addDays($days), 'amount' => 50, 'balance' => $balance,
                'status' => $status, 'billing_type' => 'tuition',
            ]);
        }

        Livewire::test(ListCustomers::class)->filterTable('overdue')
            ->assertCanSeeTableRecords([$late])->assertCanNotSeeTableRecords($others)
            ->assertTableColumnStateSet('overdue_invoices_count', 2, $late);
    }
}
