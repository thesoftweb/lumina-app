<?php

namespace Tests\Feature;

use App\Filament\Resources\Levels\Pages\ManageLevels;
use App\Filament\Resources\Subjects\Pages\ManageSubjects;
use App\Filament\Resources\Terms\Pages\ManageTerms;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Level;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReferenceDataResourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_can_create_edit_and_delete_a_subject(): void
    {
        $this->actingAs($this->user('coordinator'));

        Livewire::test(ManageSubjects::class)
            ->callAction('create', ['name' => 'Robótica', 'code' => 'ROB'])
            ->assertHasNoFormErrors();

        $subject = Subject::where('code', 'ROB')->firstOrFail();

        Livewire::test(ManageSubjects::class)
            ->callAction(TestAction::make('edit')->table($subject), ['name' => 'Robótica Educacional', 'code' => 'ROB'])
            ->assertHasNoFormErrors();
        $this->assertSame('Robótica Educacional', $subject->fresh()->name);
    }

    public function test_subject_code_must_be_unique(): void
    {
        $this->actingAs($this->user('coordinator'));
        Subject::create(['name' => 'Matemática', 'code' => 'MAT']);

        Livewire::test(ManageSubjects::class)
            ->callAction('create', ['name' => 'Matemática 2', 'code' => 'MAT'])
            ->assertHasFormErrors(['code' => 'unique']);
    }

    public function test_subject_linked_to_a_classroom_cannot_be_deleted(): void
    {
        $subject = Subject::create(['name' => 'História', 'code' => 'HIS']);
        $classroom = Classroom::create(['name' => '6º A', 'level_id' => $this->level()->id]);
        $classroom->subjects()->attach($subject);

        $this->assertNotNull($subject->deletionBlockedReason());
        $this->assertNull(Subject::create(['name' => 'Livre'])->deletionBlockedReason());
    }

    public function test_level_with_classrooms_cannot_be_deleted(): void
    {
        $level = $this->level();
        $this->assertNull($level->deletionBlockedReason());

        Classroom::create(['name' => '6º A', 'level_id' => $level->id]);

        $this->assertNotNull($level->deletionBlockedReason());
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $level->delete();
    }

    public function test_delete_button_is_cancelled_with_a_notification_when_level_is_in_use(): void
    {
        $this->actingAs($this->user('admin'));
        $level = $this->level();
        Classroom::create(['name' => '6º A', 'level_id' => $level->id]);

        Livewire::test(ManageLevels::class)
            ->callAction(TestAction::make('delete')->table($level))
            ->assertNotified('Não é possível excluir');

        $this->assertDatabaseHas('levels', ['id' => $level->id]);
    }

    public function test_coordinator_can_create_a_level(): void
    {
        $this->actingAs($this->user('coordinator'));

        Livewire::test(ManageLevels::class)
            ->callAction('create', ['name' => 'Ensino Médio', 'description' => '1º ao 3º ano'])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('levels', ['name' => 'Ensino Médio']);
    }

    public function test_term_requires_unique_order_per_year_and_valid_dates(): void
    {
        $this->actingAs($this->user('coordinator'));
        AcademicYear::create(['description' => '2027', 'year' => 2027, 'start_at' => '2027-02-01', 'end_at' => '2027-12-15', 'is_default' => false]);

        Livewire::test(ManageTerms::class)
            ->callAction('create', ['year' => 2027, 'order' => 1, 'name' => '1º Bimestre', 'start_date' => '2027-02-01', 'end_date' => '2027-04-30'])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('terms', ['year' => 2027, 'order' => 1]);

        Livewire::test(ManageTerms::class)
            ->callAction('create', ['year' => 2027, 'order' => 1, 'name' => 'Duplicado', 'start_date' => '2027-05-01', 'end_date' => '2027-07-01'])
            ->assertHasFormErrors(['order' => 'unique']);

        Livewire::test(ManageTerms::class)
            ->callAction('create', ['year' => 2027, 'order' => 2, 'name' => '2º Bimestre', 'start_date' => '2027-07-01', 'end_date' => '2027-05-01'])
            ->assertHasFormErrors(['end_date']);
    }

    public function test_term_with_grades_cannot_be_deleted(): void
    {
        $term = Term::create(['name' => '1º Bimestre', 'year' => 2026, 'order' => 1]);
        $this->assertNull($term->deletionBlockedReason());

        $enrollmentId = $this->enrollmentWithGrade($term);

        $this->assertNotNull($term->fresh()->deletionBlockedReason());
        $this->assertNotNull($enrollmentId);
    }

    public function test_teacher_cannot_open_reference_data_pages(): void
    {
        $this->actingAs($this->user('teacher'));

        Livewire::test(ManageSubjects::class)->assertForbidden();
        Livewire::test(ManageLevels::class)->assertForbidden();
        Livewire::test(ManageTerms::class)->assertForbidden();
    }

    private function user(string $role): User
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::create(['name' => 'Escola', 'email' => 'escola@example.test']);
        $user = User::create(['name' => ucfirst($role), 'email' => "{$role}@example.test", 'password' => 'password', 'company_id' => $company->id]);
        $user->assignRole($role);

        if ($role === 'teacher') {
            Teacher::create(['name' => 'Professor', 'email' => 'teacher@example.test', 'user_id' => $user->id]);
        }

        return $user;
    }

    private function level(): Level
    {
        return Level::create(['name' => 'Fundamental II']);
    }

    private function enrollmentWithGrade(Term $term): int
    {
        $level = $this->level();
        $classroom = Classroom::create(['name' => '6º A', 'level_id' => $level->id]);
        $subject = Subject::create(['name' => 'Matemática', 'code' => 'MAT']);
        $assignmentId = \DB::table('classroom_subject')->insertGetId(['classroom_id' => $classroom->id, 'subject_id' => $subject->id, 'created_at' => now(), 'updated_at' => now()]);
        $customer = \App\Models\Customer::create(['name' => 'Responsável', 'document' => '30000000001']);
        $student = \App\Models\Student::create(['customer_id' => $customer->id, 'name' => 'Aluno', 'date_of_birth' => '2014-01-01', 'gender' => 'M']);
        $enrollment = \App\Models\Enrollment::create(['student_id' => $student->id, 'customer_id' => $customer->id, 'classroom_id' => $classroom->id, 'enrollment_date' => '2026-02-01', 'status' => 'active']);
        \App\Models\Grade::create(['enrollment_id' => $enrollment->id, 'classroom_subject_id' => $assignmentId, 'term_id' => $term->id, 'grade' => 8]);

        return $enrollment->id;
    }
}
