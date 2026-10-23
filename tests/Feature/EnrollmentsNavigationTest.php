<?php

namespace Tests\Feature;

use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Filament\Resources\Enrollments\Pages\CreateEnrollment;
use App\Filament\Resources\Enrollments\Pages\EditEnrollment;
use App\Filament\Resources\Enrollments\Pages\ListEnrollments;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\Plan;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EnrollmentsNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollment_row_opens_view_and_keeps_document_actions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->get(EnrollmentResource::getUrl('create'))
            ->assertOk()
            ->assertSee('enrollment-wizard.css');

        $customer = Customer::create(['name' => 'Responsável', 'document' => '12345678901']);
        $student = Student::create([
            'name' => 'Ana', 'customer_id' => $customer->id,
            'date_of_birth' => '2015-03-10', 'gender' => 'F',
        ]);
        $level = Level::create(['name' => 'Fundamental']);
        $classroom = Classroom::create(['name' => 'Turma A', 'level_id' => $level->id]);
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'customer_id' => $customer->id,
            'classroom_id' => $classroom->id,
            'enrollment_date' => '2026-02-01', 'status' => 'active',
        ]);

        $page = Livewire::test(ListEnrollments::class)
            ->assertCanSeeTableRecords([$enrollment])
            ->assertTableActionExists('edit')
            ->assertTableActionDoesNotExist('view');

        $this->assertSame(EnrollmentResource::getUrl('view', ['record' => $enrollment]), $page->instance()->getTable()->getRecordUrl($enrollment));
        $this->assertArrayHasKey('view', EnrollmentResource::getPages());

        Livewire::test(CreateEnrollment::class)
            ->assertSee('Aluno e período')
            ->assertSee('Pagamento')
            ->assertSee('Documentos');

        Livewire::test(ListEnrollments::class)
            ->filterTable('classroom_id', $classroom->id)
            ->assertCanSeeTableRecords([$enrollment]);
        Livewire::test(ListEnrollments::class)
            ->filterTable('status', 'canceled')
            ->assertCanNotSeeTableRecords([$enrollment]);
        Livewire::test(ListEnrollments::class)
            ->filterTable('enrollment_date', ['from' => '2026-03-01'])
            ->assertCanNotSeeTableRecords([$enrollment]);

        Livewire::test(EditEnrollment::class, ['record' => $enrollment->id])
            ->assertActionExists('printEnrollmentContract')
            ->assertActionExists('createEnrollmentInvoice')
            ->assertActionExists('createTuitionInvoice');

        $year = AcademicYear::create(['description' => '2026', 'year' => 2026, 'is_default' => true]);
        $plan = Plan::create([
            'name' => 'Mensal', 'base_amount' => 100, 'has_discount' => false,
            'discount_type' => 'none', 'discount_value' => 0,
            'final_value' => 100, 'is_active' => true,
        ]);
        $classroom->plans()->attach($plan);

        Livewire::test(CreateEnrollment::class)
            ->fillForm([
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'classroom_id' => $classroom->id,
                'enrollment_date' => '2026-03-10',
                'status' => 'active',
                'plan_id' => $plan->id,
                'day_of_payment' => 10,
            ])
            ->assertDontSee('Matrícula bloqueada')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'classroom_id' => $classroom->id,
            'plan_id' => $plan->id,
            'day_of_payment' => 10,
        ]);

        Livewire::test(CreateEnrollment::class)
            ->fillForm(['student_id' => $student->id, 'academic_year_id' => $year->id])
            ->assertSee('Matrícula bloqueada')
            ->assertSee('Abrir matrícula existente')
            ->goToNextWizardStep()
            ->assertHasFormErrors(['student_id'])
            ->assertNotified('Matrícula bloqueada');

        Livewire::test(CreateEnrollment::class)
            ->fillForm([
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'classroom_id' => $classroom->id,
                'enrollment_date' => '2026-03-11',
                'status' => 'active',
                'plan_id' => $plan->id,
                'day_of_payment' => 10,
            ])
            ->call('create')
            ->assertHasFormErrors(['student_id'])
            ->assertNotified('Matrícula bloqueada');

        $this->assertSame(1, Enrollment::query()->where('academic_year_id', $year->id)->count());
    }
}
