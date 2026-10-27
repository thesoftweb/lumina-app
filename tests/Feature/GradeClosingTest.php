<?php

namespace Tests\Feature;

use App\Enums\AssessmentMode;
use App\Filament\Pages\Gradebook;
use App\Filament\Resources\Classrooms\Pages\EditClassroom;
use App\Filament\Resources\Classrooms\RelationManagers\SubjectWorkloadsRelationManager;
use App\Filament\Resources\Enrollments\Pages\EditEnrollment;
use App\Filament\Resources\Enrollments\RelationManagers\GradeRelationManager;
use App\Filament\Resources\Terms\Pages\ManageTerms;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomSubject;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeChange;
use App\Models\Level;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Fechamento de bimestre, correção de notas pela secretaria, carga horária e boletim em PDF. */
class GradeClosingTest extends TestCase
{
    use RefreshDatabase;

    private User $secretary;

    private User $teacherUser;

    private Classroom $classroom;

    private ClassroomSubject $math;

    private Enrollment $enrollment;

    private Term $term;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(10, 0));
        $this->seed(RolePermissionSeeder::class);

        $company = Company::create(['name' => 'Escola Ceci', 'email' => 'escola@example.test']);
        $this->secretary = User::factory()->create(['name' => 'Secretária', 'company_id' => $company->id]);
        $this->secretary->assignRole('secretary');
        $this->teacherUser = User::factory()->create(['name' => 'Professora', 'company_id' => $company->id]);
        $this->teacherUser->assignRole('teacher');
        $teacher = Teacher::create(['name' => 'Professora', 'user_id' => $this->teacherUser->id]);

        $this->classroom = Classroom::create(['name' => '3º A', 'level_id' => Level::create(['name' => 'Fundamental'])->id]);
        $this->classroom->teachers()->attach($teacher);
        $this->classroom->subjects()->attach(Subject::create(['name' => 'Matemática'])->id);
        $this->math = $this->classroom->classroomSubjects()->sole();
        $this->math->teachers()->attach($teacher);

        $year = AcademicYear::create(['description' => '2026', 'year' => 2026, 'start_at' => '2026-02-02', 'end_at' => '2026-12-18']);
        $customer = Customer::create(['name' => 'Maria', 'document' => '096.234.214-94']);
        $student = Student::create(['name' => 'Ana Clara', 'customer_id' => $customer->id, 'date_of_birth' => '2017-03-10', 'gender' => 'F']);
        $this->enrollment = Enrollment::create([
            'student_id' => $student->id, 'customer_id' => $customer->id, 'classroom_id' => $this->classroom->id,
            'academic_year_id' => $year->id, 'enrollment_date' => '2026-02-01', 'status' => 'active',
        ]);
        $this->term = Term::create(['name' => '1º Bimestre', 'year' => 2026, 'order' => 1, 'start_date' => '2026-02-02', 'end_date' => '2026-04-30']);
    }

    private function grades()
    {
        return Livewire::test(GradeRelationManager::class, ['ownerRecord' => $this->enrollment, 'pageClass' => EditEnrollment::class]);
    }

    public function test_office_closes_and_reopens_a_term(): void
    {
        $this->actingAs($this->secretary);

        Livewire::test(ManageTerms::class)
            ->callTableAction('close', $this->term)
            ->assertNotified('Bimestre fechado');

        $this->term->refresh();
        $this->assertTrue($this->term->isClosed());
        $this->assertTrue($this->term->closedBy->is($this->secretary));

        Livewire::test(ManageTerms::class)
            ->assertTableActionHidden('close', $this->term)
            ->callTableAction('reopen', $this->term);

        $this->assertFalse($this->term->fresh()->isClosed());
    }

    public function test_teacher_cannot_change_grades_of_a_closed_term(): void
    {
        $this->actingAs($this->teacherUser);

        Livewire::test(Gradebook::class)
            ->call('updateTableColumnState', 'grade_1', (string) $this->enrollment->id, '8');
        $this->assertSame('8.00', Grade::sole()->grade_1);

        $this->term->update(['closed_at' => now()]);

        Livewire::test(Gradebook::class)
            ->assertSee('Bimestre fechado pela secretaria')
            ->call('updateTableColumnState', 'grade_1', (string) $this->enrollment->id, '3');
        $this->assertSame('8.00', Grade::sole()->grade_1);
    }

    public function test_office_launches_and_corrects_grades_with_a_reason_even_in_a_closed_term(): void
    {
        $this->term->update(['closed_at' => now()]);
        $this->actingAs($this->secretary);

        $this->grades()
            ->callTableAction('launch', data: ['classroom_subject_id' => $this->math->id, 'term_id' => $this->term->id, 'grade_1' => '5', 'grade_2' => '6', 'reason' => 'Notas entregues em papel'])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Nota salva');

        $grade = Grade::sole();
        $this->assertSame('5.50', $grade->grade);
        $this->assertNull($grade->teacher_id);

        $this->grades()
            ->callTableAction('correct', $grade, ['grade_1' => '5', 'grade_2' => '6', 'recovery_grade' => '8', 'reason' => 'Recuperação'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('7.00', $grade->fresh()->grade);
        $changes = GradeChange::query()->orderBy('id')->get();
        $this->assertCount(2, $changes);
        $this->assertNull($changes[0]->before);
        $this->assertSame('Recuperação', $changes[1]->reason);
        $this->assertTrue($changes[1]->user->is($this->secretary));
        $this->assertSame('Recuperação: — → 8,00; Média: 5,50 → 7,00', GradeRelationManager::describeChange($changes[1]->before, $changes[1]->after));

        $this->grades()
            ->callTableAction('correct', $grade, ['grade_1' => '9', 'grade_2' => '6', 'reason' => ''])
            ->assertHasTableActionErrors(['reason' => 'required']);
    }

    public function test_concept_classroom_is_corrected_with_a_concept(): void
    {
        $this->classroom->update(['assessment_mode' => AssessmentMode::Concept]);
        $this->actingAs($this->secretary);
        $concept = Company::sole()->conceptScales()->where('code', 'B')->sole();

        $this->grades()
            ->callTableAction('launch', data: ['classroom_subject_id' => $this->math->id, 'term_id' => $this->term->id, 'concept_scale_id' => $concept->id, 'reason' => 'Ficha do professor'])
            ->assertHasNoTableActionErrors();

        $this->assertSame($concept->id, Grade::sole()->concept_scale_id);
        $this->assertNull(Grade::sole()->grade);
    }

    public function test_teachers_do_not_see_the_office_grade_tab(): void
    {
        $this->actingAs($this->secretary);
        $this->assertTrue(GradeRelationManager::canViewForRecord($this->enrollment, EditEnrollment::class));
        $this->actingAs($this->teacherUser);
        $this->assertFalse(GradeRelationManager::canViewForRecord($this->enrollment, EditEnrollment::class));
    }

    public function test_workload_is_set_per_subject_of_the_classroom(): void
    {
        $this->actingAs($this->secretary);

        Livewire::test(SubjectWorkloadsRelationManager::class, ['ownerRecord' => $this->classroom, 'pageClass' => EditClassroom::class])
            ->assertCanSeeTableRecords([$this->math])
            ->call('updateTableColumnState', 'workload_hours', (string) $this->math->id, '160');

        $this->assertSame(160, $this->math->fresh()->workload_hours);
        $this->assertSame('160 h', SubjectWorkloadsRelationManager::getBadge($this->classroom, EditClassroom::class));
    }

    public function test_report_card_is_a_real_pdf_to_view_or_download(): void
    {
        $this->actingAs($this->secretary);

        $view = $this->get(route('report-cards.show', $this->enrollment))->assertOk();
        $this->assertSame('application/pdf', $view->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $view->getContent());
        $this->assertStringContainsString('inline', $view->headers->get('Content-Disposition'));

        $download = $this->get(route('report-cards.pdf', $this->enrollment))->assertOk();
        $this->assertStringContainsString('attachment; filename=boletim-ana-clara-2026.pdf', $download->headers->get('Content-Disposition'));
    }
}
