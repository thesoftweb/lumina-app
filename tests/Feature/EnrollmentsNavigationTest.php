<?php

namespace Tests\Feature;

use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Filament\Resources\Enrollments\Pages\EditEnrollment;
use App\Filament\Resources\Enrollments\Pages\ListEnrollments;
use App\Models\Classroom;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EnrollmentsNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollment_row_opens_edit_without_view_action_and_keeps_document_actions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

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

        $this->assertSame(EnrollmentResource::getUrl('edit', ['record' => $enrollment]), $page->instance()->getTable()->getRecordUrl($enrollment));
        $this->assertArrayNotHasKey('view', EnrollmentResource::getPages());

        Livewire::test(EditEnrollment::class, ['record' => $enrollment->id])
            ->assertActionExists('printSchoolContract')
            ->assertActionExists('pdfSchoolContract')
            ->assertActionExists('createEnrollmentInvoice')
            ->assertActionExists('createTuitionInvoice');
    }
}
