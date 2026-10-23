<?php

namespace Tests\Feature;

use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Models\City;
use App\Models\Company;
use App\Models\State;
use App\Models\User;
use App\Rules\Cnpj;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        $this->get(CompanyResource::getUrl('index'))
            ->assertOk()
            ->assertSeeLivewire(EditCompany::class)
            ->assertSeeInOrder(['Instituição', 'Documentos', 'Avaliação', 'Integração financeira']);
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

    public function test_identification_address_and_signer_are_saved_and_reloaded(): void
    {
        $company = $this->signIn();
        State::forceCreate(['code' => 25, 'name' => 'Paraíba', 'abbreviation' => 'PB']);
        $city = City::forceCreate(['code' => 2513703, 'name' => 'Santa Rita', 'state_id' => 25]);

        Livewire::test(EditCompany::class)
            ->fillForm([
                'tax_id' => '12.abc.345/01de-35', 'inep_code' => '25012345', 'phone' => '(83) 3333-4444',
                'website' => 'https://escola.example.test', 'postal_code' => '58300-000', 'state' => 25, 'city_id' => $city->id,
                'address' => 'Av. Principal', 'address_number' => '100', 'neighborhood' => 'Centro',
                'document_signer_name' => 'Joana Diretora', 'document_signer_role' => 'Diretora',
            ])
            ->call('save')->assertHasNoFormErrors();

        $company->refresh();
        $this->assertSame('12.ABC.345/01DE-35', $company->tax_id);
        $this->assertSame('Av. Principal, 100 - Centro - Santa Rita/PB - CEP 58300-000', $company->full_address);
        $this->assertSame(['Joana Diretora', 'Diretora'], [$company->document_signer_name, $company->document_signer_role]);

        Livewire::test(EditCompany::class)->assertSet('data.state', 25)->assertSet('data.city_id', $city->id);
    }

    public function test_invalid_cnpj_inep_and_incomplete_signer_are_rejected(): void
    {
        $this->signIn();

        Livewire::test(EditCompany::class)
            ->fillForm(['tax_id' => '11.222.333/0001-82', 'inep_code' => '1234', 'document_signer_name' => 'Joana Diretora'])
            ->call('save')->assertHasFormErrors(['tax_id', 'inep_code', 'document_signer_role']);
    }

    public function test_cnpj_rule_checks_numeric_and_alphanumeric_digits(): void
    {
        foreach (['11.222.333/0001-81', '11222333000181', '12.ABC.345/01DE-35', '12abc34501de35'] as $valid) {
            $this->assertTrue(Cnpj::isValid($valid), $valid);
        }

        foreach (['11.222.333/0001-80', '00.000.000/0000-00', '12.ABC.345/01DE-36', '1122233300018', ''] as $invalid) {
            $this->assertFalse(Cnpj::isValid($invalid), $invalid);
        }
    }

    public function test_logo_is_uploaded_replaced_and_removed_from_disk(): void
    {
        Storage::fake('public');
        $company = $this->signIn();

        Livewire::test(EditCompany::class)
            ->fillForm(['logo_path' => UploadedFile::fake()->image('logo.png', 200, 100)])
            ->call('save')->assertHasNoFormErrors();

        $first = $company->fresh()->logo_path;
        $this->assertStringStartsWith(Company::LOGO_DIRECTORY.'/', $first);
        Storage::disk('public')->assertExists($first);
        $this->assertStringStartsWith('data:image/png;base64,', $company->fresh()->logoDataUri());

        // Na tela, enviar outra imagem substitui a atual no campo.
        Livewire::test(EditCompany::class)
            ->fillForm(['logo_path' => [UploadedFile::fake()->image('nova.jpg', 200, 100)]])
            ->call('save')->assertHasNoFormErrors();

        $second = $company->fresh()->logo_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        Livewire::test(EditCompany::class)
            ->fillForm(['logo_path' => null])
            ->call('save')->assertHasNoFormErrors();

        $this->assertNull($company->fresh()->logo_path);
        $this->assertNull($company->fresh()->logoDataUri());
        Storage::disk('public')->assertMissing($second);
    }

    public function test_logo_must_be_a_small_image(): void
    {
        Storage::fake('public');
        $this->signIn();

        Livewire::test(EditCompany::class)
            ->fillForm(['logo_path' => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf')])
            ->call('save')->assertHasFormErrors(['logo_path']);

        Livewire::test(EditCompany::class)
            ->fillForm(['logo_path' => UploadedFile::fake()->image('enorme.png')->size(3000)])
            ->call('save')->assertHasFormErrors(['logo_path']);
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
