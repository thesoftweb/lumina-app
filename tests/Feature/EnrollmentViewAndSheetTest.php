<?php

namespace Tests\Feature;

use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Filament\Resources\Enrollments\Pages\EditEnrollment;
use App\Filament\Resources\Enrollments\Pages\ViewEnrollment;
use App\Models\AcademicYear;
use App\Models\City;
use App\Models\Classroom;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\Plan;
use App\Models\State;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EnrollmentViewAndSheetTest extends TestCase
{
    use RefreshDatabase;

    private Enrollment $enrollment;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        // Estados e cidades usam o código IBGE da UF (PB = 25) em customers.state e cities.state_id.
        State::forceCreate(['id' => 12, 'code' => 25, 'name' => 'Paraíba', 'abbreviation' => 'PB']);
        State::forceCreate(['id' => 25, 'code' => 51, 'name' => 'Mato Grosso', 'abbreviation' => 'MT']);
        $city = City::forceCreate(['code' => 2513703, 'name' => 'Santa Rita', 'state_id' => 25]);

        $customer = Customer::create([
            'name' => 'Maria Responsável', 'document' => '096.234.214-94',
            'phone' => '(83) 99999-0000', 'address' => 'Rua das Flores', 'address_number' => '10',
            'neighborhood' => 'Centro', 'postal_code' => '58000-000', 'state' => '25', 'city_id' => $city->id,
        ]);
        $this->student = Student::create([
            'name' => 'Ana Clara', 'customer_id' => $customer->id, 'date_of_birth' => '2015-03-10',
            'gender' => 'F', 'affiliation_1' => 'Maria Responsável', 'degree_of_kinship' => 'mãe',
            'state_of_birth' => '25', 'city_of_birth' => 'Santa Rita',
        ]);
        $classroom = Classroom::create(['name' => 'Turma A', 'level_id' => Level::create(['name' => 'Fundamental'])->id]);
        $year = AcademicYear::create(['description' => '2026.1', 'year' => 2026, 'is_default' => true]);
        $plan = Plan::create([
            'name' => 'Mensal', 'base_amount' => 100, 'has_discount' => false,
            'discount_type' => 'none', 'discount_value' => 0, 'final_value' => 100, 'is_active' => true,
        ]);
        $classroom->plans()->attach($plan);

        $this->enrollment = Enrollment::create([
            'student_id' => $this->student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id,
            'plan_id' => $plan->id, 'enrollment_date' => '2026-02-01', 'status' => 'active', 'day_of_payment' => 10,
            'use_custom_discount' => true, 'discount_type' => 'percentage', 'discount_value' => 10,
            'doc_historical_delivered' => true, 'doc_photo_3x4_delivered' => true,
        ]);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create(['name' => 'Secretária Teste']);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_view_page_shows_summary_tabs_and_actions(): void
    {
        $this->actingAsRole('admin');

        Livewire::test(ViewEnrollment::class, ['record' => $this->enrollment->id])
            ->assertOk()
            ->assertSee('Ana Clara')
            ->assertSee('Matrícula nº '.$this->enrollment->number())
            ->assertSee(['Aluno', 'Responsável', 'Financeiro', 'Faturas'])
            ->assertSee('2 de 6 entregues')
            ->assertSee('90,00')
            ->assertSee('10% (personalizado)')
            ->assertActionExists('printEnrollmentSheet')
            ->assertActionExists('edit')
            ->assertActionExists('createTuitionInvoice');
    }

    public function test_edit_page_is_organized_in_tabs_and_ignores_its_own_enrollment(): void
    {
        $this->actingAsRole('admin');

        Livewire::test(EditEnrollment::class, ['record' => $this->enrollment->id])
            ->assertSee(['Aluno e período', 'Matrícula', 'Pagamento', 'Documentos'])
            ->assertDontSee('Matrícula bloqueada')
            ->assertActionExists('view')
            ->assertActionExists('printEnrollmentSheet')
            ->fillForm(['day_of_payment' => 15])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(15, (int) $this->enrollment->fresh()->day_of_payment);
    }

    public function test_sheet_is_ready_to_print_and_sign(): void
    {
        $this->actingAsRole('admin');

        $this->get(route('enrollments.sheet', ['enrollment' => $this->enrollment, 'print' => 1]))
            ->assertOk()
            ->assertSee('Ficha de matrícula')
            ->assertSee('Nº <strong>'.$this->enrollment->number().'</strong>', false)
            ->assertSee(['Ana Clara', '10/03/2015', 'Turma A', 'Fundamental', '2026.1', 'Maria Responsável', 'Mãe'])
            ->assertSee('Santa Rita - PB')
            ->assertSee('Rua das Flores, 10 - Centro - Santa Rita/PB - CEP 58000-000')
            ->assertSee(['Mensal', '90,00', 'Todo dia 10'])
            ->assertSee('Documentos entregues (2 de 6)')
            ->assertSee(['Responsável legal · CPF 096.234.214-94', 'Secretaria escolar'])
            ->assertSee('por Secretária Teste')
            ->assertSee('window.print()', false);
    }

    public function test_global_search_finds_enrollments_by_student_name(): void
    {
        $this->actingAsRole('admin');

        $results = EnrollmentResource::getGlobalSearchResults('Ana');

        $this->assertCount(1, $results);
        $this->assertSame('Ana Clara', (string) $results->first()->title);
        $this->assertSame(['Turma' => 'Turma A', 'Ano letivo' => '2026.1'], $results->first()->details);
    }

    public function test_sheet_requires_an_authorized_user(): void
    {
        $url = route('enrollments.sheet', ['enrollment' => $this->enrollment]);

        $this->get($url)->assertRedirect(route('filament.admin.auth.login'));

        $this->actingAsRole('teacher');
        $this->get($url)->assertForbidden();
    }
}
