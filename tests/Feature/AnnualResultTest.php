<?php

namespace Tests\Feature;

use App\Enums\AssessmentMode;
use App\Enums\TranscriptResult;
use App\Filament\Pages\ReportCards;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\ClassroomSubject;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Level;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TranscriptYear;
use App\Models\User;
use App\Services\Academic\AnnualResultCalculator;
use App\Services\Academic\SubjectResult;
use App\Services\Academic\YearClosing;
use App\Services\ReportCardService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Resultado final do ano, fechamento do ano letivo e boletim com a situação. */
class AnnualResultTest extends TestCase
{
    use RefreshDatabase;

    private User $secretary;

    private Company $company;

    private Classroom $classroom;

    private AcademicYear $year;

    private ClassroomSubject $math;

    private ClassroomSubject $portuguese;

    private Enrollment $enrollment;

    /** @var array<int, Term> */
    private array $terms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 12, 20)->setTime(10, 0));
        $this->seed(RolePermissionSeeder::class);

        $this->company = Company::create(['name' => 'Escola Ceci', 'email' => 'escola@example.test', 'minimum_passing_grade' => 6, 'minimum_attendance_percent' => 75]);
        $this->secretary = User::factory()->create(['name' => 'Secretária', 'company_id' => $this->company->id]);
        $this->secretary->assignRole('secretary');
        $this->actingAs($this->secretary);

        $this->classroom = Classroom::create(['name' => '3º A', 'grade_label' => '3º ano', 'school_days' => 200, 'level_id' => Level::create(['name' => 'Fundamental'])->id]);
        $this->classroom->subjects()->attach([Subject::create(['name' => 'Matemática'])->id, Subject::create(['name' => 'Língua Portuguesa'])->id]);
        $this->math = $this->classroom->classroomSubjects()->whereHas('subject', fn ($query) => $query->where('name', 'Matemática'))->sole();
        $this->portuguese = $this->classroom->classroomSubjects()->whereHas('subject', fn ($query) => $query->where('name', 'Língua Portuguesa'))->sole();
        $this->math->update(['workload_hours' => 160]);
        $this->portuguese->update(['workload_hours' => 200]);

        $this->year = AcademicYear::create(['description' => '2026', 'year' => 2026, 'start_at' => '2026-02-02', 'end_at' => '2026-12-18']);
        $this->terms = [
            Term::create(['name' => '1º Bimestre', 'year' => 2026, 'order' => 1]),
            Term::create(['name' => '2º Bimestre', 'year' => 2026, 'order' => 2]),
        ];
        $this->enrollment = $this->enroll('Ana Clara');
    }

    private function enroll(string $name): Enrollment
    {
        $customer = Customer::create(['name' => "Responsável de {$name}", 'document' => (string) random_int(10000000000, 99999999999)]);
        $student = Student::create(['name' => $name, 'customer_id' => $customer->id, 'date_of_birth' => '2017-03-10', 'gender' => 'F']);

        return Enrollment::create([
            'student_id' => $student->id, 'customer_id' => $customer->id, 'classroom_id' => $this->classroom->id,
            'academic_year_id' => $this->year->id, 'enrollment_date' => '2026-02-01', 'status' => 'active',
        ]);
    }

    /** @param  array<int, float|null>  $values  nota por bimestre */
    private function grades(Enrollment $enrollment, ClassroomSubject $subject, array $values): void
    {
        foreach ($values as $index => $value) {
            if ($value !== null) {
                Grade::create(['enrollment_id' => $enrollment->id, 'classroom_subject_id' => $subject->id, 'term_id' => $this->terms[$index]->id, 'grade' => $value]);
            }
        }
    }

    private function attendance(Enrollment $enrollment, int $present, int $absent): void
    {
        $teacher = Teacher::create(['name' => 'Professora']);
        $date = now()->setDate(2026, 3, 2);

        foreach (range(1, $present + $absent) as $day) {
            Attendance::create(['enrollment_id' => $enrollment->id, 'classroom_id' => $this->classroom->id, 'teacher_id' => $teacher->id, 'date' => $date->copy()->addDays($day), 'present' => $day <= $present]);
        }
    }

    private function annual(?Enrollment $enrollment = null)
    {
        return app(AnnualResultCalculator::class)->forEnrollment(($enrollment ?? $this->enrollment)->fresh(), $this->company->fresh());
    }

    public function test_average_of_the_terms_decides_each_subject_and_the_year(): void
    {
        $this->grades($this->enrollment, $this->math, [7, 8]);
        $this->grades($this->enrollment, $this->portuguese, [6, 6]);
        $this->attendance($this->enrollment, 19, 1);

        $result = $this->annual();

        $this->assertSame(TranscriptResult::Approved, $result->result);
        $math = $result->subjects->firstWhere('name', 'Matemática');
        $this->assertSame(7.5, $math->average);
        $this->assertSame('7,50', $math->finalLabel());
        $this->assertSame(SubjectResult::APPROVED, $math->status);
        $this->assertSame(95.0, $result->attendance['percentage']);
        $this->assertSame(360, $result->totalWorkload());
        $this->assertSame('Média mínima 6,0 · frequência mínima 75%', $result->rulesLabel());
    }

    public function test_average_below_the_minimum_fails_the_subject_and_the_year(): void
    {
        $this->grades($this->enrollment, $this->math, [5, 6]);
        $this->grades($this->enrollment, $this->portuguese, [8, 8]);

        $result = $this->annual();

        $this->assertSame(SubjectResult::FAILED, $result->subjects->firstWhere('name', 'Matemática')->status);
        $this->assertSame(TranscriptResult::Failed, $result->result);
    }

    public function test_attendance_below_the_minimum_fails_even_with_good_grades(): void
    {
        $this->grades($this->enrollment, $this->math, [9, 9]);
        $this->grades($this->enrollment, $this->portuguese, [9, 9]);
        $this->attendance($this->enrollment, 7, 3);

        $this->assertSame(TranscriptResult::FailedAttendance, $this->annual()->result);
    }

    public function test_missing_term_grade_keeps_the_year_in_progress(): void
    {
        $this->grades($this->enrollment, $this->math, [9, null]);
        $this->grades($this->enrollment, $this->portuguese, [8, 8]);

        $result = $this->annual();

        $this->assertSame(TranscriptResult::InProgress, $result->result);
        $this->assertSame(['Matemática'], $result->pendingSubjects()->pluck('name')->all());
        $this->assertNull($result->subjects->firstWhere('name', 'Matemática')->average);
        $this->assertSame(9.0, $result->subjects->firstWhere('name', 'Matemática')->partialAverage);
    }

    public function test_concept_classroom_uses_the_concept_of_the_last_term(): void
    {
        $this->classroom->update(['assessment_mode' => AssessmentMode::Concept]);
        $concepts = $this->company->conceptScales()->pluck('id', 'code');

        foreach ([$this->math, $this->portuguese] as $subject) {
            Grade::create(['enrollment_id' => $this->enrollment->id, 'classroom_subject_id' => $subject->id, 'term_id' => $this->terms[0]->id, 'concept_scale_id' => $concepts['D']]);
            Grade::create(['enrollment_id' => $this->enrollment->id, 'classroom_subject_id' => $subject->id, 'term_id' => $this->terms[1]->id, 'concept_scale_id' => $concepts['B']]);
        }

        $result = $this->annual();

        $this->assertSame(TranscriptResult::Approved, $result->result);
        $this->assertSame('B', $result->subjects->first()->finalLabel());
    }

    public function test_closing_the_classroom_records_each_student_in_the_transcript(): void
    {
        $this->grades($this->enrollment, $this->math, [7, 8]);
        $this->grades($this->enrollment, $this->portuguese, [6, 6]);
        $this->attendance($this->enrollment, 18, 2);
        $other = $this->enroll('Bruno');
        $this->grades($other, $this->math, [4, 5]);
        $this->grades($other, $this->portuguese, [7, 7]);

        $years = app(YearClosing::class)->closeClassroom($this->classroom, $this->year, $this->secretary);

        $this->assertCount(2, $years);
        $record = TranscriptYear::query()->where('enrollment_id', $this->enrollment->id)->sole();
        $this->assertSame(TranscriptYear::INTERNAL, $record->source);
        $this->assertSame(2026, $record->year);
        $this->assertSame('3º ano', $record->grade_label);
        $this->assertSame('Escola Ceci', $record->school_name);
        $this->assertSame(TranscriptResult::Approved, $record->result);
        $this->assertSame('90.00', $record->attendance_percent);
        $this->assertSame(200, $record->school_days);
        $this->assertSame(360, $record->total_workload);
        $this->assertSame(['Língua Portuguesa' => '6,00', 'Matemática' => '7,50'], $record->subjects->mapWithKeys(fn ($subject) => [$subject->name => $subject->resultLabel()])->all());
        $this->assertSame(TranscriptResult::Failed, TranscriptYear::query()->where('enrollment_id', $other->id)->sole()->result);
    }

    public function test_closing_is_blocked_while_grades_are_missing(): void
    {
        $this->grades($this->enrollment, $this->math, [7, null]);

        try {
            app(YearClosing::class)->closeClassroom($this->classroom, $this->year, $this->secretary);
            $this->fail('Deveria bloquear o fechamento.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Ana Clara (Língua Portuguesa, Matemática)', $exception->getMessage());
        }

        $this->assertSame(0, TranscriptYear::count());
    }

    public function test_class_council_adjustment_needs_a_reason_and_survives_closing_again(): void
    {
        $this->grades($this->enrollment, $this->math, [5, 5.5]);
        $this->grades($this->enrollment, $this->portuguese, [8, 8]);
        $closing = app(YearClosing::class);

        try {
            $closing->closeEnrollment($this->enrollment, $this->secretary, TranscriptResult::Approved);
            $this->fail('Deveria exigir o motivo.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('adjustment_reason', $exception->errors());
        }

        $record = $closing->closeEnrollment($this->enrollment, $this->secretary, TranscriptResult::Approved, 'Conselho de classe de 18/12');
        $this->assertTrue($record->isAdjusted());
        $this->assertSame(TranscriptResult::Failed, $record->computed_result);

        $again = $closing->closeEnrollment($this->enrollment->fresh(), $this->secretary);
        $this->assertSame(TranscriptResult::Approved, $again->result);
        $this->assertSame('Conselho de classe de 18/12', $again->adjustment_reason);
    }

    public function test_transferred_student_is_recorded_with_partial_grades(): void
    {
        $this->grades($this->enrollment, $this->math, [7, null]);

        $record = app(YearClosing::class)->closeEnrollment($this->enrollment, $this->secretary, TranscriptResult::Transferred, 'Transferido em 10/06');

        $this->assertSame(TranscriptResult::Transferred, $record->result);
        $this->assertSame('7,00', $record->subjects->firstWhere('name', 'Matemática')->resultLabel());
        $this->assertSame('—', $record->subjects->firstWhere('name', 'Língua Portuguesa')->resultLabel());
    }

    public function test_report_cards_page_closes_the_year_and_shows_the_result(): void
    {
        $this->grades($this->enrollment, $this->math, [7, 8]);
        $this->grades($this->enrollment, $this->portuguese, [6, 6]);

        Livewire::test(ReportCards::class)
            ->assertTableColumnStateSet('annual_result', TranscriptResult::Approved, $this->enrollment)
            ->callAction('closeYear', ['classroom_id' => $this->classroom->id, 'academic_year_id' => $this->year->id])
            ->assertNotified('Ano letivo fechado');

        $this->assertSame(1, TranscriptYear::count());

        Livewire::test(ReportCards::class)->assertSee('Ano fechado');
    }

    public function test_report_card_shows_final_grades_situation_and_rules(): void
    {
        $this->grades($this->enrollment, $this->math, [7, 8]);
        $this->grades($this->enrollment, $this->portuguese, [5, 5]);

        $html = view('report-cards.pdf', app(ReportCardService::class)->forEnrollment($this->enrollment))->render();

        $this->assertStringContainsString('Boletim escolar', $html);
        $this->assertStringContainsString('3º ano – 3º A', $html);
        $this->assertStringContainsString('<strong>7,50</strong>', $html);
        $this->assertStringContainsString('Reprovado', $html);
        $this->assertStringContainsString('Média mínima 6,0 · frequência mínima 75%', $html);
    }
}
