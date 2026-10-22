<?php

namespace Tests\Feature;

use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $role = 'admin'): Company
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::create(['name' => 'Escola', 'email' => 'escola@example.test', 'asaas_key' => 'existing-secret']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $company;
    }

    public function test_menu_opens_the_company_form_directly(): void
    {
        $this->signIn();

        $this->get(CompanyResource::getUrl('index'))->assertOk()->assertSeeLivewire(EditCompany::class);
        $this->assertFalse(CompanyResource::canCreate());
    }

    public function test_save_updates_the_existing_company_and_preserves_blank_secret(): void
    {
        $company = $this->signIn();

        Livewire::test(EditCompany::class)
            ->assertSet('data.asaas_key', null)
            ->fillForm(['name' => 'Escola atualizada', 'email' => 'contato@example.test', 'minimum_passing_grade' => 7, 'environment' => 'sandbox', 'asaas_key' => ''])
            ->call('save')->assertHasNoFormErrors()->assertNotified('Dados da empresa atualizados');

        $this->assertSame('Escola atualizada', $company->fresh()->name);
        $this->assertSame('existing-secret', $company->fresh()->asaas_key);
        $this->assertSame(4, $company->conceptScales()->count());
        $this->assertDatabaseCount('companies', 1);
    }

    public function test_teacher_secretary_and_other_company_urls_are_denied(): void
    {
        $this->signIn('teacher');
        Livewire::test(EditCompany::class)->assertForbidden();

        auth()->user()->syncRoles(['secretary']);
        Livewire::test(EditCompany::class)->assertForbidden();

        auth()->user()->syncRoles(['admin']);
        $other = Company::create(['name' => 'Outra', 'email' => 'outra@example.test']);
        Livewire::test(EditCompany::class, ['record' => $other->id])->assertForbidden();
    }

    public function test_invalid_grade_and_environment_are_rejected(): void
    {
        $this->signIn();
        Livewire::test(EditCompany::class)
            ->fillForm(['minimum_passing_grade' => 11, 'environment' => 'invalid'])
            ->call('save')->assertHasFormErrors(['minimum_passing_grade', 'environment']);
    }

    public function test_a_new_secret_replaces_the_existing_one(): void
    {
        $company = $this->signIn();
        Livewire::test(EditCompany::class)
            ->fillForm(['asaas_key' => 'replacement-secret'])
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame('replacement-secret', $company->fresh()->asaas_key);
        $this->assertNotSame('replacement-secret', $company->fresh()->getRawOriginal('asaas_key'));
    }

    public function test_first_setup_redirects_to_creation_when_no_company_exists(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->get(CompanyResource::getUrl('index'))
            ->assertRedirect(CompanyResource::getUrl('create'));
    }
}
