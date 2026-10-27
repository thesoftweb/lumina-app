<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\PreEnrollmentStatus;
use App\Filament\Resources\AcademicYears\Pages\EditAcademicYear;
use App\Filament\Resources\AcademicYears\Pages\ViewAcademicYear;
use App\Filament\Resources\AcademicYears\RelationManagers\AdmissionOffersRelationManager;
use App\Filament\Resources\PreEnrollments\Pages\ViewPreEnrollment;
use App\Models\AcademicYear;
use App\Models\AdmissionOffer;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\Plan;
use App\Models\PreEnrollment;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Academic\ClassroomYearCopier;
use App\Services\Admissions\OffersFromClassrooms;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Turmas de 2027 copiadas das de 2026, uma vaga por turma, e a família escolhendo a turma na ficha. */
class ClassroomOffersForPreEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year2026;

    private AcademicYear $year2027;

    private Classroom $infantil2026;

    private Classroom $ballet2026;

    private Plan $infantil2027Plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 27)->setTime(10, 0));
        $this->seed(RolePermissionSeeder::class);

        $secretary = User::factory()->create();
        $secretary->assignRole('secretary');
        $this->actingAs($secretary);

        $this->year2026 = AcademicYear::create(['description' => '2026.1', 'year' => 2026, 'is_default' => true]);
        $this->year2027 = AcademicYear::create(['description' => '2027.1', 'year' => 2027, 'admissions_open' => true]);

        $infantil = Level::create(['name' => 'ED INFANTIL']);
        $this->infantil2026 = Classroom::create(['name' => 'INFANTIL 3', 'grade_label' => 'Infantil 3', 'school_days' => 200, 'level_id' => $infantil->id, 'academic_year_id' => $this->year2026->id]);
        $this->infantil2026->plans()->attach([$this->plan('MENSALIDADE INFANTIL', 225, $this->year2026)->id, $this->plan('BOLSA INFANTIL 30%', 225, $this->year2026)->id]);
        $this->infantil2026->subjects()->attach(Subject::create(['name' => 'Linguagem'])->id, ['workload_hours' => 120]);
        $this->infantil2026->teachers()->attach(Teacher::create(['name' => 'Juliana'])->id);

        $this->ballet2026 = Classroom::create(['name' => 'BALLET', 'level_id' => Level::create(['name' => 'EXTRA'])->id, 'academic_year_id' => $this->year2026->id]);

        $this->infantil2027Plan = $this->plan('MENSALIDADE INFANTIL', 235, $this->year2027);
    }

    public function test_copier_creates_the_selected_classrooms_in_the_new_year_without_touching_the_old_ones(): void
    {
        $result = app(ClassroomYearCopier::class)->copy($this->year2026, $this->year2027, [$this->infantil2026->id]);

        $copy = $result['created']->sole();
        $this->assertSame('INFANTIL 3', $copy->name);
        $this->assertSame($this->year2027->id, $copy->academic_year_id);
        $this->assertSame('Infantil 3', $copy->grade_label);
        $this->assertSame(200, (int) $copy->school_days);
        $this->assertEquals(120, $copy->classroomSubjects()->sole()->workload_hours);
        $this->assertSame([$this->infantil2027Plan->id], $copy->plans()->pluck('plans.id')->all(), 'só o plano de 2027 com o mesmo nome');
        $this->assertSame(0, $copy->teachers()->count(), 'professores não são copiados');
        $this->assertSame(['BOLSA INFANTIL 30% (INFANTIL 3)'], $result['missing_plans']);

        $this->assertSame(2, $this->infantil2026->plans()->count(), 'a turma de 2026 não muda');
        $this->assertFalse(Classroom::query()->where('academic_year_id', $this->year2027->id)->where('name', 'BALLET')->exists());

        // Rodar de novo não duplica
        $again = app(ClassroomYearCopier::class)->copy($this->year2026, $this->year2027, [$this->infantil2026->id]);
        $this->assertTrue($again['created']->isEmpty());
        $this->assertSame(['INFANTIL 3'], $again['skipped']);
    }

    public function test_copy_action_on_the_academic_year_page(): void
    {
        Livewire::test(ViewAcademicYear::class, ['record' => $this->year2027->id])
            ->callAction('copyClassrooms', ['source_id' => $this->year2026->id, 'classroom_ids' => [$this->infantil2026->id]])
            ->assertHasNoActionErrors();

        $this->assertSame(['INFANTIL 3'], Classroom::query()->where('academic_year_id', $this->year2027->id)->pluck('name')->all());
    }

    public function test_offers_are_created_from_the_year_classrooms_with_optional_vacancies(): void
    {
        $copy = app(ClassroomYearCopier::class)->copy($this->year2026, $this->year2027, [$this->infantil2026->id])['created']->sole();

        Livewire::test(AdmissionOffersRelationManager::class, ['ownerRecord' => $this->year2027, 'pageClass' => EditAcademicYear::class])
            ->callTableAction('offersFromClassrooms')
            ->assertHasNoTableActionErrors();

        $offer = AdmissionOffer::query()->sole();
        $this->assertSame($copy->id, $offer->classroom_id);
        $this->assertSame('INFANTIL 3', $offer->name);
        $this->assertSame($this->infantil2027Plan->id, $offer->plan_id);
        $this->assertNull($offer->vacancies);

        $this->assertTrue(app(OffersFromClassrooms::class)->create($this->year2027)->isEmpty(), 'rodar de novo não duplica');
    }

    public function test_offers_are_ordered_by_level_then_classroom(): void
    {
        $fundamental = Level::create(['name' => 'FUNDAMENTAL I']);
        Classroom::create(['name' => '1 ANO FUNDAMENTAL', 'level_id' => $fundamental->id, 'academic_year_id' => $this->year2027->id]);
        app(ClassroomYearCopier::class)->copy($this->year2026, $this->year2027, [$this->infantil2026->id]);

        app(OffersFromClassrooms::class)->create($this->year2027);

        $this->assertSame(['INFANTIL 3', '1 ANO FUNDAMENTAL'], AdmissionOffer::query()->pluck('name')->all());
        $this->assertSame([1, 2], AdmissionOffer::query()->pluck('sort_order')->all());
    }

    public function test_offer_classroom_must_belong_to_the_offer_year(): void
    {
        $this->expectException(ValidationException::class);

        AdmissionOffer::create(['academic_year_id' => $this->year2027->id, 'classroom_id' => $this->infantil2026->id, 'name' => 'Errada']);
    }

    public function test_family_chooses_the_classroom_on_the_public_form(): void
    {
        $copy = app(ClassroomYearCopier::class)->copy($this->year2026, $this->year2027, [$this->infantil2026->id])['created']->sole();
        app(OffersFromClassrooms::class)->create($this->year2027);

        $this->get('/pre-matricula/2027')
            ->assertOk()
            ->assertSee('Turmas e valores 2027')
            ->assertSee('Escolha a turma pretendida')
            ->assertSee('INFANTIL 3')
            ->assertSee('235,00')
            ->assertDontSee('vagas disponíveis');

        $this->assertSame($copy->id, AdmissionOffer::query()->sole()->classroom_id);
    }

    public function test_conversion_comes_with_the_classroom_chosen_by_the_family(): void
    {
        $copy = app(ClassroomYearCopier::class)->copy($this->year2026, $this->year2027, [$this->infantil2026->id])['created']->sole();
        $offer = app(OffersFromClassrooms::class)->create($this->year2027)->sole();

        $lead = PreEnrollment::create([
            'academic_year_id' => $this->year2027->id, 'admission_offer_id' => $offer->id, 'status' => PreEnrollmentStatus::New,
            'student_name' => 'Lia', 'student_birth_date' => '2023-03-01',
            'guardian_name' => 'Marta', 'guardian_document' => '529.982.247-25', 'guardian_phone' => '83998765432',
        ]);

        Livewire::test(ViewPreEnrollment::class, ['record' => $lead->id])
            ->mountAction('convert')
            ->assertActionDataSet(['classroom_id' => $copy->id, 'plan_id' => $this->infantil2027Plan->id])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $enrollment = Enrollment::query()->sole();
        $this->assertSame($copy->id, $enrollment->classroom_id);
        $this->assertSame($this->year2027->id, $enrollment->academic_year_id);
        $this->assertSame(EnrollmentStatus::Reserved, $enrollment->status);
    }

    private function plan(string $name, float $amount, AcademicYear $year): Plan
    {
        return Plan::create(['academic_year_id' => $year->id, 'name' => $name, 'base_amount' => $amount, 'discount_type' => 'none', 'is_active' => true]);
    }
}
