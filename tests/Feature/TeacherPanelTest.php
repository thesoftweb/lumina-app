<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\ClassroomSubject;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ReportCardService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_role_permission_seeder_is_idempotent(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseHas('permissions', ['name' => 'view_report_cards']);
    }

    private function scenario(): array
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::create(['name' => 'Professor', 'email' => 'teacher@example.test', 'password' => 'password']);
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
            'date_of_birth' => '2012-07-20',
            'gender' => 'M',
            'affiliation_1' => 'Filiação 1',
            'affiliation_2' => 'Filiação 2',
            'observations' => 'Observação pedagógica',
        ]);
    }
}
