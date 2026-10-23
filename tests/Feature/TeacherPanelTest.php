<?php

namespace Tests\Feature;

use App\Enums\AssessmentMode;
use App\Filament\Pages\Birthdays;
use App\Filament\Pages\Gradebook;
use App\Filament\Pages\ReportCards;
use App\Filament\Resources\Teachers\Schemas\TeacherForm;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\ClassroomSubject;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\GradeCalculationService;
use App\Services\ReportCardService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class TeacherPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_only_views_students_from_assigned_classrooms(): void
    {
        [$user, $teacher, $classroom, $enrollment] = $this->scenario();
        $other = $this->student('Aluno de outra turma', '20000000002');

        $this->assertTrue($user->can('view', $enrollment->student));
        $this->assertFalse($user->can('view', $other));
        $this->assertTrue($classroom->teachers()->whereKey($teacher->id)->exists());
    }

    public function test_report_card_contains_terms_grades_and_attendance_summary(): void
    {
        [, $teacher, $classroom, $enrollment, $assignment, $term] = $this->scenario();

        Grade::create([
            'enrollment_id' => $enrollment->id,
            'classroom_subject_id' => $assignment->id,
            'term_id' => $term->id,
            'teacher_id' => $teacher->id,
            'grade' => 8.5,
        ]);
        Attendance::create([
            'enrollment_id' => $enrollment->id,
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'date' => '2026-03-10',
            'present' => false,
            'justified' => true,
        ]);

        $report = app(ReportCardService::class)->forEnrollment($enrollment);

        $this->assertSame('Matemática', $report['subjects']->first()['name']);
        $this->assertEquals(8.5, $report['subjects']->first()['grades'][$term->id]);
        $this->assertSame(1, $report['attendance']['absences']);
        $this->assertSame(1, $report['attendance']['justified']);
        $this->assertSame(0.0, $report['attendance']['percentage']);
    }

    public function test_birthdays_table_starts_with_current_month_and_can_filter_another_month(): void
    {
        [$user, , , $enrollment] = $this->scenario();
        $this->actingAs($user);

        Livewire::test(Birthdays::class)
            ->assertCanSeeTableRecords([$enrollment->student])
            ->filterTable('month', now()->addMonth()->month)
            ->assertCanNotSeeTableRecords([$enrollment->student]);
    }

    public function test_teacher_can_filter_and_preview_allowed_report_cards(): void
    {
        [$user, , $classroom, $enrollment] = $this->scenario();
        $this->actingAs($user);

        $component = Livewire::test(ReportCards::class)
            ->assertCanSeeTableRecords([$enrollment])
            ->filterTable('classroom_id', $classroom->id)
            ->assertCanSeeTableRecords([$enrollment])
            ->assertSee($enrollment->student->name)
            ->assertSee('Prévia')
            ->assertSee('Gerar PDF');

        $this->assertTrue($component->instance()->getTable()->getAction('preview')->isModalSlideOver());
    }

    public function test_teacher_can_launch_grade_and_comments_directly_in_gradebook_table(): void
    {
        [$user, $teacher, , $enrollment, $assignment, $term] = $this->scenario();
        $this->actingAs($user);

        Livewire::test(Gradebook::class)
            ->assertCanSeeTableRecords([$enrollment])
            ->call('updateTableColumnState', 'grade_1', (string) $enrollment->id, '8')
            ->call('updateTableColumnState', 'grade_2', (string) $enrollment->id, '6')
            ->call('updateTableColumnState', 'recovery_grade', (string) $enrollment->id, '9')
            ->call('updateTableColumnState', 'comments', (string) $enrollment->id, 'Ótima evolução.');

        $this->assertDatabaseHas('grades', [
            'enrollment_id' => $enrollment->id,
            'classroom_subject_id' => $assignment->id,
            'term_id' => $term->id,
            'teacher_id' => $teacher->id,
            'grade_1' => 8,
            'grade_2' => 6,
            'recovery_grade' => 9,
            'grade' => 8.5,
            'comments' => 'Ótima evolução.',
        ]);
        $report = app(ReportCardService::class)->forEnrollment($enrollment);
        $this->assertSame(8.5, $report['subjects']->first()['annual_average']);
    }

    public function test_recovery_uses_the_two_highest_grades_without_reducing_average(): void
    {
        $calculator = app(GradeCalculationService::class);

        $this->assertSame(7.0, $calculator->calculate(8, 6));
        $this->assertSame(8.5, $calculator->calculate(8, 6, 9));
        $this->assertSame(7.0, $calculator->calculate(8, 6, 5));
        $this->assertNull($calculator->calculate(8, null, 9));
    }

    public function test_teacher_can_launch_a_concept_for_a_concept_classroom(): void
    {
        [$user, $teacher, $classroom, $enrollment, $assignment, $term] = $this->scenario();
        $classroom->update(['assessment_mode' => AssessmentMode::Concept]);
        $concept = $user->company->conceptScales()->where('code', 'B')->firstOrFail();
        $this->actingAs($user);

        Livewire::test(Gradebook::class)
            ->assertCanSeeTableRecords([$enrollment])
            ->call('updateTableColumnState', 'concept_scale_id', (string) $enrollment->id, (string) $concept->id);

        $this->assertDatabaseHas('grades', [
            'enrollment_id' => $enrollment->id,
            'classroom_subject_id' => $assignment->id,
            'term_id' => $term->id,
            'teacher_id' => $teacher->id,
            'concept_scale_id' => $concept->id,
            'grade' => null,
        ]);
        $report = app(ReportCardService::class)->forEnrollment($enrollment);
        $this->assertSame('B', $report['subjects']->first()['grades'][$term->id]);
        $this->assertNull($report['subjects']->first()['annual_average']);
    }

    public function test_classroom_assessment_mode_cannot_change_after_results_exist(): void
    {
        [, $teacher, $classroom, $enrollment, $assignment, $term] = $this->scenario();
        Grade::create([
            'enrollment_id' => $enrollment->id,
            'classroom_subject_id' => $assignment->id,
            'term_id' => $term->id,
            'teacher_id' => $teacher->id,
            'grade_1' => 8,
            'grade_2' => 7,
            'grade' => 7.5,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $classroom->update(['assessment_mode' => AssessmentMode::Concept]);
    }

    public function test_role_permission_seeder_is_idempotent(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseHas('permissions', ['name' => 'view_report_cards']);
    }

    public function test_teacher_user_link_is_available_in_both_directions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::create(['name' => 'Docente', 'email' => 'docente@example.test', 'password' => 'password']);
        $user->assignRole('teacher');
        $teacher = Teacher::create(['name' => 'Docente', 'user_id' => $user->id]);

        $this->assertTrue($user->fresh()->teacher->is($teacher));
        $this->assertTrue($teacher->fresh()->user->is($user));
        $this->assertTrue($user->fresh()->canAccessPanel(\Filament\Panel::make()));
        $this->actingAs($user);
        $this->assertArrayHasKey($user->id, TeacherForm::availableUserOptions($teacher));
    }

    private function scenario(): array
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::create(['name' => 'Escola Teste', 'email' => 'escola@example.test']);
        $user = User::create(['name' => 'Professor', 'email' => 'teacher@example.test', 'password' => 'password', 'company_id' => $company->id]);
        $user->assignRole('teacher');
        $teacher = Teacher::create(['name' => 'Professor', 'email' => 'teacher@example.test', 'user_id' => $user->id]);
        $classroom = Classroom::create(['name' => '6º A', 'level_id' => 1]);
        $classroom->teachers()->attach($teacher);

        $subjectId = DB::table('subjects')->insertGetId(['name' => 'Matemática', 'code' => 'MAT', 'created_at' => now(), 'updated_at' => now()]);
        $assignmentId = DB::table('classroom_subject')->insertGetId(['classroom_id' => $classroom->id, 'subject_id' => $subjectId, 'created_at' => now(), 'updated_at' => now()]);
        $assignment = ClassroomSubject::findOrFail($assignmentId);
        $assignment->teachers()->attach($teacher);

        $year = AcademicYear::create(['description' => '2026', 'year' => 2026, 'start_at' => '2026-02-01', 'end_at' => '2026-12-15']);
        $student = $this->student('Aluno da turma', '20000000001');
        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'customer_id' => $student->customer_id,
            'classroom_id' => $classroom->id,
            'academic_year_id' => $year->id,
            'enrollment_date' => '2026-02-01',
            'status' => 'active',
        ]);
        $termId = DB::table('terms')->insertGetId(['name' => '1º Bimestre', 'year' => 2026, 'order' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return [$user, $teacher, $classroom, $enrollment, $assignment, \App\Models\Term::findOrFail($termId)];
    }

    private function student(string $name, string $document): Student
    {
        $customer = Customer::create(['name' => 'Responsável', 'document' => $document]);

        return Student::create([
            'customer_id' => $customer->id,
            'name' => $name,
            'date_of_birth' => now()->subYears(14)->toDateString(),
            'gender' => 'M',
            'affiliation_1' => 'Filiação 1',
            'affiliation_2' => 'Filiação 2',
            'observations' => 'Observação pedagógica',
        ]);
    }
}
