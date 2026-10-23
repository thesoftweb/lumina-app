<?php

namespace Tests\Feature;

use App\Filament\Pages\Gradebook;
use App\Filament\Pages\TakeAttendance;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\ClassroomSubject;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use App\Services\ReportCardService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\Term2027Seeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AcademicYearProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_with_grades_cannot_be_removed_by_model_or_database(): void
    {
        $s = $this->scenario();

        try {
            $s['assignment']->delete();
            $this->fail('A disciplina com notas foi removida.');
        } catch (ValidationException) {
            // esperado
        }

        $this->expectException(QueryException::class);
        DB::table('classroom_subject')->where('id', $s['assignment']->id)->delete();
    }

    public function test_grades_survive_every_blocked_deletion(): void
    {
        $s = $this->scenario();

        foreach ([
            fn () => $s['assignment']->subject->delete(),
            fn () => $s['teacher']->delete(),
            fn () => $s['classroom']->delete(),
            fn () => $s['enrollment2026']->delete(),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Exclusão com histórico não foi bloqueada.');
            } catch (ValidationException) {
                // esperado
            }
        }

        $this->assertDatabaseCount('grades', 1);
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_deleting_enrollment_row_directly_does_not_cascade_to_grades(): void
    {
        $s = $this->scenario();

        $this->expectException(QueryException::class);
        DB::table('enrollments')->where('id', $s['enrollment2026']->id)->delete();
    }

    public function test_student_cannot_have_two_non_canceled_enrollments_in_the_same_year(): void
    {
        $s = $this->scenario();
        $data = [
            'student_id' => $s['enrollment2026']->student_id,
            'classroom_id' => $s['classroom']->id,
            'academic_year_id' => $s['year2026']->id,
            'enrollment_date' => '2026-03-01',
            'status' => 'active',
        ];

        try {
            Enrollment::create($data);
            $this->fail('Matrícula duplicada foi aceita.');
        } catch (ValidationException) {
            // esperado
        }

        // Em outro ano é permitido (rematrícula) e matrícula cancelada não conta.
        Enrollment::create([...$data, 'academic_year_id' => $s['year2027']->id, 'status' => 'reserved']);
        Enrollment::create([...$data, 'status' => 'canceled']);

        $this->assertSame(3, Enrollment::where('student_id', $data['student_id'])->count());
    }

    public function test_only_one_academic_year_is_default(): void
    {
        $s = $this->scenario();
        $s['year2026']->update(['is_default' => true]);
        $s['year2027']->update(['is_default' => true]);

        $this->assertFalse($s['year2026']->fresh()->is_default);
        $this->assertTrue($s['year2027']->fresh()->is_default);
        $this->assertSame(1, AcademicYear::where('is_default', true)->count());
    }

    public function test_academic_year_with_enrollments_cannot_be_deleted(): void
    {
        $s = $this->scenario();

        $this->expectException(ValidationException::class);
        $s['year2026']->delete();
    }

    public function test_gradebook_only_lists_enrollments_of_the_selected_term_year(): void
    {
        $s = $this->scenario();
        $this->actingAs($s['user']);

        Livewire::test(Gradebook::class)
            ->set('data.term_id', $s['term2026']->id)
            ->assertCanSeeTableRecords([$s['enrollment2026']])
            ->assertCanNotSeeTableRecords([$s['enrollment2027']])
            ->set('data.term_id', $s['term2027']->id)
            ->assertCanSeeTableRecords([$s['enrollment2027']])
            ->assertCanNotSeeTableRecords([$s['enrollment2026']]);
    }

    public function test_enrollment_rejects_terms_from_other_years(): void
    {
        $s = $this->scenario();

        $this->assertTrue($s['enrollment2026']->acceptsTerm($s['term2026']));
        $this->assertFalse($s['enrollment2026']->acceptsTerm($s['term2027']));
        $this->assertFalse($s['enrollment2027']->acceptsTerm($s['term2026']));
    }

    public function test_attendance_only_lists_enrollments_covering_the_date(): void
    {
        $s = $this->scenario();
        $this->actingAs($s['user']);

        $in2026 = Livewire::test(TakeAttendance::class)
            ->set('classroomId', $s['classroom']->id)
            ->set('date', '2026-09-10')
            ->call('loadStudents')
            ->get('attendance');
        $in2027 = Livewire::test(TakeAttendance::class)
            ->set('classroomId', $s['classroom']->id)
            ->set('date', '2027-03-10')
            ->call('loadStudents')
            ->get('attendance');

        $this->assertSame([$s['enrollment2026']->id], array_keys($in2026));
        $this->assertSame([$s['enrollment2027']->id], array_keys($in2027));
    }

    public function test_2026_report_card_is_unchanged_by_2027_data(): void
    {
        $s = $this->scenario();
        $before = app(ReportCardService::class)->forEnrollment($s['enrollment2026']);

        Grade::create([
            'enrollment_id' => $s['enrollment2027']->id,
            'classroom_subject_id' => $s['assignment']->id,
            'term_id' => $s['term2027']->id,
            'grade' => 5,
        ]);
        $after = app(ReportCardService::class)->forEnrollment($s['enrollment2026']->fresh());

        $this->assertSame([$s['term2026']->id], $after['terms']->pluck('id')->all());
        $this->assertEquals($before['subjects']->all(), $after['subjects']->all());
        $this->assertEquals($before['attendance'], $after['attendance']);
    }

    public function test_term_2027_seeder_is_idempotent_and_keeps_manual_dates(): void
    {
        $this->seed(Term2027Seeder::class);
        Term::where('year', 2027)->where('order', 1)->update(['start_date' => '2027-02-08']);
        $this->seed(Term2027Seeder::class);

        $this->assertSame(4, Term::where('year', 2027)->count());
        $this->assertSame('2027-02-08', Term::where('year', 2027)->where('order', 1)->first()->start_date);
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

        $subject = Subject::findOrFail(DB::table('subjects')->insertGetId(['name' => 'Matemática', 'code' => 'MAT', 'created_at' => now(), 'updated_at' => now()]));
        $assignment = ClassroomSubject::findOrFail(DB::table('classroom_subject')->insertGetId(['classroom_id' => $classroom->id, 'subject_id' => $subject->id, 'created_at' => now(), 'updated_at' => now()]));
        $assignment->teachers()->attach($teacher);

        $year2026 = AcademicYear::create(['description' => '2026', 'year' => 2026, 'start_at' => '2026-02-01', 'end_at' => '2026-12-15', 'is_default' => false]);
        $year2027 = AcademicYear::create(['description' => '2027', 'year' => 2027, 'start_at' => '2027-02-01', 'end_at' => '2027-12-15', 'is_default' => false]);
        $term2026 = Term::findOrFail(DB::table('terms')->insertGetId(['name' => '1º Bimestre', 'year' => 2026, 'order' => 1, 'created_at' => now(), 'updated_at' => now()]));
        $term2027 = Term::findOrFail(DB::table('terms')->insertGetId(['name' => '1º Bimestre', 'year' => 2027, 'order' => 1, 'created_at' => now(), 'updated_at' => now()]));

        $enrollment2026 = $this->enrollment($this->student('Aluno 2026', '20000000001'), $classroom, $year2026, 'active');
        $enrollment2027 = $this->enrollment($this->student('Aluno 2027', '20000000002'), $classroom, $year2027, 'active');

        Grade::create([
            'enrollment_id' => $enrollment2026->id,
            'classroom_subject_id' => $assignment->id,
            'term_id' => $term2026->id,
            'teacher_id' => $teacher->id,
            'grade' => 8,
        ]);
        Attendance::create([
            'enrollment_id' => $enrollment2026->id,
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'date' => '2026-03-10',
            'present' => true,
        ]);

        return compact('user', 'teacher', 'classroom', 'assignment', 'year2026', 'year2027', 'term2026', 'term2027', 'enrollment2026', 'enrollment2027');
    }

    private function enrollment(Student $student, Classroom $classroom, AcademicYear $year, string $status): Enrollment
    {
        return Enrollment::create([
            'student_id' => $student->id,
            'customer_id' => $student->customer_id,
            'classroom_id' => $classroom->id,
            'academic_year_id' => $year->id,
            'enrollment_date' => $year->start_at,
            'status' => $status,
        ]);
    }

    private function student(string $name, string $document): Student
    {
        $customer = Customer::create(['name' => 'Responsável', 'document' => $document]);

        return Student::create([
            'customer_id' => $customer->id,
            'name' => $name,
            'date_of_birth' => now()->subYears(14)->toDateString(),
            'gender' => 'M',
        ]);
    }
}
