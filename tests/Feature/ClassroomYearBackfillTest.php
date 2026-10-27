<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Turmas antigas recebem 2026, e um ano letivo novo não vira padrão sozinho. Relatório de testes, falha 2. */
class ClassroomYearBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_classrooms_without_year_are_assigned_to_2026_unless_they_have_another_year(): void
    {
        $year2026 = AcademicYear::create(['description' => '2026.1', 'year' => 2026, 'is_default' => true]);
        $year2027 = AcademicYear::create(['description' => '2027.1', 'year' => 2027]);
        $level = Level::create(['name' => 'Fundamental']);

        $with2026 = Classroom::create(['name' => '1 ANO', 'level_id' => $level->id]);
        $empty = Classroom::create(['name' => 'Bellet', 'level_id' => $level->id]);
        $mixed = Classroom::create(['name' => 'Mista', 'level_id' => $level->id]);
        $already2027 = Classroom::create(['name' => '1 ANO 2027', 'level_id' => $level->id, 'academic_year_id' => $year2027->id]);

        $this->enroll($with2026, $year2026->id, 'Ana');
        $this->enroll($with2026, null, 'Bia');
        $this->enroll($mixed, $year2027->id, 'Caio');

        (require database_path('migrations/2026_10_27_000004_assign_2026_to_classrooms_without_year.php'))->up();

        $this->assertSame($year2026->id, $with2026->fresh()->academic_year_id);
        $this->assertSame($year2026->id, $empty->fresh()->academic_year_id);
        $this->assertNull($mixed->fresh()->academic_year_id, 'turma com matrícula de outro ano fica para revisão');
        $this->assertSame($year2027->id, $already2027->fresh()->academic_year_id);
    }

    public function test_a_new_academic_year_is_not_default_unless_marked(): void
    {
        $year2026 = AcademicYear::create(['description' => '2026.1', 'year' => 2026, 'is_default' => true]);
        $year2027 = AcademicYear::create(['description' => '2027.1', 'year' => 2027]);

        $this->assertFalse($year2027->fresh()->is_default);
        $this->assertTrue($year2026->fresh()->is_default);

        $year2027->update(['is_default' => true]);

        $this->assertFalse($year2026->fresh()->is_default);
    }

    private function enroll(Classroom $classroom, ?int $academicYearId, string $name): void
    {
        $customer = Customer::create(['name' => "Responsável {$name}", 'document' => uniqid()]);
        $student = Student::create(['name' => $name, 'customer_id' => $customer->id, 'date_of_birth' => '2019-01-01', 'gender' => 'F']);

        Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $academicYearId,
            'enrollment_date' => '2026-02-01', 'status' => EnrollmentStatus::Active->value,
        ]);
    }
}
