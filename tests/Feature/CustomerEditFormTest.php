<?php

namespace Tests\Feature;

use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Models\City;
use App\Models\Customer;
use App\Models\State;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerEditFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_and_address_in_other_tabs_can_be_saved_together(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $state = new State;
        $state->code = 35;
        $state->name = 'São Paulo';
        $state->abbreviation = 'SP';
        $state->save();

        // Como no seeder do IBGE, cities.state_id guarda o código da UF, não o id da tabela states.
        $city = new City;
        $city->code = 3550308;
        $city->name = 'São Paulo';
        $city->state_id = $state->code;
        $city->save();

        $otherCity = new City;
        $otherCity->code = 3304557;
        $otherCity->name = 'Rio de Janeiro';
        $otherCity->state_id = 33;
        $otherCity->save();

        $customer = Customer::create(['name' => 'Responsável', 'document' => '123.456.789-00']);

        Livewire::test(EditCustomer::class, ['record' => $customer->id])
            ->assertFormFieldExists('name')
            ->assertFormFieldExists('email')
            ->assertFormFieldExists('city_id')
            ->set('data.state', 35)
            ->assertFormFieldExists('city_id', fn ($field): bool => array_keys($field->getOptions()) === [$city->id])
            ->fillForm([
                'email' => 'novo@example.test', 'phone' => '(11) 99999-9999',
                'postal_code' => '01000-000', 'state' => 35, 'city_id' => $city->id,
                'address' => 'Rua da Escola', 'address_number' => '42',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $customer->refresh();
        $this->assertSame('novo@example.test', $customer->email);
        $this->assertSame('Rua da Escola', $customer->address);
        $this->assertSame($city->id, $customer->city_id);
        $this->assertSame('SP', $customer->city->state->abbreviation);
    }
}
