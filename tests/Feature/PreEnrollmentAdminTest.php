<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\PreEnrollmentStatus;
use App\Enums\PreEnrollmentType;
use App\Filament\Resources\AcademicYears\AcademicYearResource;
use App\Filament\Resources\AcademicYears\Pages\EditAcademicYear;
use App\Filament\Resources\AcademicYears\RelationManagers\AdmissionOffersRelationManager;
use App\Filament\Resources\Classrooms\ClassroomResource;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Filament\Resources\Enrollments\Pages\CreateEnrollment;
use App\Filament\Resources\Enrollments\Pages\ViewEnrollment;
use App\Filament\Resources\Plans\PlanResource;
use App\Filament\Resources\PreEnrollments\Pages\CreatePreEnrollment;
use App\Filament\Resources\PreEnrollments\Pages\ListPreEnrollments;
use App\Filament\Resources\PreEnrollments\Pages\ViewPreEnrollment;
use App\Filament\Resources\PreEnrollments\PreEnrollmentResource;
use App\Models\AcademicYear;
use App\Models\AdmissionOffer;
use App\Models\Classroom;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Level;
use App\Models\Plan;
use App\Models\PreEnrollment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Admissions\EnrollmentFee;
use App\Services\Admissions\PreEnrollmentConverter;
use App\Services\Asaas\ChargeOperations;
use App\Services\InvoiceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Pré-matrículas no painel: lista por ano, contatos, desistência e conversão em matrícula do ano da ficha. */
class PreEnrollmentAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $secretary;

    private AcademicYear $year2026;

    private AcademicYear $year2027;

    private Level $level;

    private Classroom $classroom2026;

    private Classroom $classroom2027;

    private Plan $plan2026;

    private Plan $plan2027;

    private AdmissionOffer $offer2027;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 27)->setTime(10, 0));
        $this->seed(RolePermissionSeeder::class);

        $this->secretary = User::factory()->create(['name' => 'Secretária']);
        $this->secretary->assignRole('secretary');
        $this->actingAs($this->secretary);

        $this->year2026 = AcademicYear::create(['description' => 'Ano letivo 2026', 'year' => 2026, 'is_default' => true]);
        $this->year2027 = AcademicYear::create(['description' => 'Ano letivo 2027', 'year' => 2027, 'is_default' => false, 'admissions_open' => true]);

        $this->level = Level::create(['name' => 'Fundamental I']);
        $this->plan2026 = $this->plan('Mensal 2026', 600, $this->year2026);
        $this->plan2027 = $this->plan('Mensal 2027', 650, $this->year2027);
        $this->classroom2026 = $this->classroom('3º A', $this->year2026, $this->plan2026);
        $this->classroom2027 = $this->classroom('3º A 2027', $this->year2027, $this->plan2027);

        $this->offer2027 = AdmissionOffer::create([
            'academic_year_id' => $this->year2027->id, 'level_id' => $this->level->id, 'plan_id' => $this->plan2027->id,
            'name' => '3º ano - Fundamental I', 'enrollment_fee' => 300,
        ]);
    }

    public function test_list_defaults_to_the_year_receiving_pre_enrollments_and_hides_other_years(): void
    {
        $lead2027 = $this->lead();
        $lead2026 = $this->lead(['academic_year_id' => $this->year2026->id, 'student_name' => 'Pedro Lima', 'admission_offer_id' => null]);

        Livewire::test(ListPreEnrollments::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$lead2027])
            ->assertCanNotSeeTableRecords([$lead2026])
            ->filterTable('academic_year_id', $this->year2026->id)
            ->assertCanSeeTableRecords([$lead2026])
            ->assertCanNotSeeTableRecords([$lead2027]);
    }

    public function test_navigation_badge_counts_new_leads_of_open_years_only(): void
    {
        $this->lead();
        $this->lead(['student_name' => 'Outra criança', 'status' => PreEnrollmentStatus::InContact]);
        $this->lead(['academic_year_id' => $this->year2026->id, 'student_name' => 'Pedro Lima', 'admission_offer_id' => null]);

        $this->assertSame('1', PreEnrollmentResource::getNavigationBadge());
    }

    public function test_secretary_registers_a_walk_in_lead_with_the_cpf_normalized(): void
    {
        Livewire::test(CreatePreEnrollment::class)
            ->fillForm([
                'academic_year_id' => $this->year2027->id,
                'admission_offer_id' => $this->offer2027->id,
                'student_name' => 'Lucas Andrade',
                'student_birth_date' => '2019-05-20',
                'guardian_name' => 'Carla Andrade',
                'guardian_document' => '529.982.247-25',
                'guardian_phone' => '(83) 99876-5432',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $lead = PreEnrollment::query()->sole();
        $this->assertSame('52998224725', $lead->guardian_document);
        $this->assertSame('83998765432', $lead->guardian_phone);
        $this->assertSame(PreEnrollmentStatus::New, $lead->status);
        $this->assertStringStartsWith('2027-', $lead->code);
    }

    public function test_converting_creates_customer_student_and_reserved_enrollment_in_the_lead_year(): void
    {
        $lead = $this->lead();

        Livewire::test(ViewPreEnrollment::class, ['record' => $lead->id])
            ->callAction('convert', [
                'classroom_id' => $this->classroom2027->id,
                'plan_id' => $this->plan2027->id,
                'day_of_payment' => 10,
                'enrollment_date' => '2026-10-27',
                'status' => EnrollmentStatus::Reserved->value,
            ])
            ->assertHasNoActionErrors()
            ->assertRedirect(EnrollmentResource::getUrl('view', ['record' => Enrollment::query()->sole()]));

        $enrollment = Enrollment::query()->sole();
        $customer = Customer::query()->sole();
        $student = Student::query()->sole();

        $this->assertSame('529.982.247-25', $customer->document);
        $this->assertSame('Carla Andrade', $customer->name);
        $this->assertSame('Lucas Andrade', $student->name);
        $this->assertSame($customer->id, $student->customer_id);
        $this->assertSame($this->year2027->id, (int) $enrollment->academic_year_id);
        $this->assertSame($this->classroom2027->id, (int) $enrollment->classroom_id);
        $this->assertSame(EnrollmentStatus::Reserved, $enrollment->status);
        $this->assertTrue((bool) $enrollment->new_student);

        $lead->refresh();
        $this->assertSame(PreEnrollmentStatus::Converted, $lead->status);
        $this->assertSame($enrollment->id, (int) $lead->enrollment_id);
        $this->assertSame($this->secretary->id, (int) $lead->converted_by);
    }

    public function test_converting_reuses_existing_customer_and_student_without_overwriting_their_data(): void
    {
        $customer = Customer::create(['name' => 'Carla A. Andrade', 'document' => '529.982.247-25', 'phone' => '83911112222']);
        $student = Student::create(['name' => 'Lucas Andrade', 'customer_id' => $customer->id, 'date_of_birth' => '2019-05-20', 'gender' => 'M']);
        $lead = $this->lead(['guardian_email' => 'carla@example.test']);

        $enrollment = app(PreEnrollmentConverter::class)->convert($lead, ['classroom_id' => $this->classroom2027->id, 'day_of_payment' => 5]);

        $this->assertSame(1, Customer::query()->count());
        $this->assertSame(1, Student::query()->count());
        $this->assertSame($student->id, (int) $enrollment->student_id);
        $this->assertTrue($enrollment->new_student, 'aluno cadastrado mas sem matrícula anterior continua novato');
        $this->assertNull($enrollment->previous_enrollment_id);
        $this->assertSame($this->plan2027->id, (int) $enrollment->plan_id, 'sem plano informado, usa o plano da vaga');

        $customer->refresh();
        $this->assertSame('Carla A. Andrade', $customer->name);
        $this->assertSame('83911112222', $customer->phone);
        $this->assertSame('carla@example.test', $customer->email, 'campo vazio é completado');
    }

    public function test_converting_a_returning_student_links_the_previous_enrollment_and_marks_reenrollment(): void
    {
        [$student, $previous] = $this->returningStudent();
        $lead = $this->lead();

        $enrollment = app(PreEnrollmentConverter::class)->convert($lead, ['classroom_id' => $this->classroom2027->id, 'day_of_payment' => 5]);

        $this->assertSame($student->id, (int) $enrollment->student_id);
        $this->assertFalse($enrollment->new_student);
        $this->assertSame($previous->id, (int) $enrollment->previous_enrollment_id);

        $lead->refresh();
        $this->assertSame(PreEnrollmentType::Reenrollment, $lead->type);
        $this->assertSame($previous->id, (int) $lead->previous_enrollment_id);
    }

    public function test_reenrollment_conversion_stores_the_negotiated_discount(): void
    {
        $this->returningStudent();
        $lead = $this->lead();

        Livewire::test(ViewPreEnrollment::class, ['record' => $lead->id])
            ->mountAction('convert')
            ->assertMountedActionModalSee('Negociação')
            ->setActionData([
                'classroom_id' => $this->classroom2027->id,
                'plan_id' => $this->plan2027->id,
                'day_of_payment' => 10,
                'enrollment_date' => '2026-10-27',
                'status' => EnrollmentStatus::Reserved->value,
                'discount_mode' => 'custom',
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'discount_reason' => 'Fidelidade: terceiro ano na escola',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $enrollment = Enrollment::query()->where('academic_year_id', $this->year2027->id)->sole();
        $this->assertTrue($enrollment->use_custom_discount);
        $this->assertSame('percentage', $enrollment->discount_type);
        $this->assertEquals(10, $enrollment->discount_value);
        $this->assertSame('Fidelidade: terceiro ano na escola', $enrollment->discount_reason);
        $this->assertEqualsWithDelta(585.0, $enrollment->tuitionBreakdown()['final_amount'], 0.001);
    }

    public function test_newcomer_conversion_hides_the_negotiation_and_rejects_a_discount(): void
    {
        $lead = $this->lead();

        Livewire::test(ViewPreEnrollment::class, ['record' => $lead->id])
            ->mountAction('convert')
            ->assertMountedActionModalSee('Mensalidade do plano')
            ->assertMountedActionModalDontSee('Negociação');

        try {
            app(PreEnrollmentConverter::class)->convert($lead, [
                'classroom_id' => $this->classroom2027->id, 'day_of_payment' => 10,
                'use_custom_discount' => true, 'discount_type' => 'fixed', 'discount_value' => 50, 'discount_reason' => 'Pedido da família',
            ]);
            $this->fail('Novato não pode ter desconto.');
        } catch (ValidationException $exception) {
            $this->assertContains(Enrollment::NEW_STUDENT_DISCOUNT_MESSAGE, $exception->errors()['use_custom_discount']);
        }

        $this->assertSame(0, Enrollment::query()->count());
        $this->assertSame(0, Customer::query()->count(), 'a transação desfaz o cadastro do responsável');
        $this->assertSame(PreEnrollmentStatus::New, $lead->refresh()->status);
    }

    public function test_negotiated_discount_requires_a_reason(): void
    {
        $this->returningStudent();

        $this->assertConversionFails($this->lead(), [
            'classroom_id' => $this->classroom2027->id,
            'use_custom_discount' => true, 'discount_type' => 'fixed', 'discount_value' => 50, 'discount_reason' => ' ',
        ], 'discount_reason');
    }

    public function test_enrollment_model_blocks_a_custom_discount_for_a_new_student(): void
    {
        [$student] = $this->returningStudent();
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $this->classroom2027->id, 'academic_year_id' => $this->year2027->id,
            'enrollment_date' => '2026-10-27', 'status' => EnrollmentStatus::Reserved->value, 'new_student' => true,
        ]);

        try {
            $enrollment->update(['use_custom_discount' => true, 'discount_type' => 'fixed', 'discount_value' => 50]);
            $this->fail('Novato não pode ter desconto.');
        } catch (ValidationException $exception) {
            $this->assertContains(Enrollment::NEW_STUDENT_DISCOUNT_MESSAGE, $exception->errors()['use_custom_discount']);
        }

        $enrollment->update(['new_student' => false, 'use_custom_discount' => true, 'discount_type' => 'fixed', 'discount_value' => 50]);
        $this->assertTrue($enrollment->refresh()->use_custom_discount);
    }

    public function test_enrollment_form_computes_new_student_from_the_history(): void
    {
        [$returning] = $this->returningStudent();
        $customer = Customer::create(['name' => 'Ana Souza', 'document' => '096.234.214-94']);
        $newcomer = Student::create(['name' => 'Bia Souza', 'customer_id' => $customer->id, 'date_of_birth' => '2020-01-10', 'gender' => 'F']);

        Livewire::test(CreateEnrollment::class)
            ->fillForm(['academic_year_id' => $this->year2027->id, 'student_id' => $returning->id])
            ->assertSchemaStateSet(['new_student' => false])
            ->fillForm(['use_custom_discount' => true])
            // Trocar para um novato marca a primeira matrícula e desfaz o desconto
            ->fillForm(['student_id' => $newcomer->id])
            ->assertSchemaStateSet(['new_student' => true, 'use_custom_discount' => false]);
    }

    public function test_enrollment_fee_is_fixed_for_newcomers_and_negotiable_for_returning_students(): void
    {
        $fee = app(EnrollmentFee::class);

        $newcomerLead = $this->lead();
        $newcomer = app(PreEnrollmentConverter::class)->convert($newcomerLead, ['classroom_id' => $this->classroom2027->id, 'day_of_payment' => 10]);

        $this->assertSame(300.0, $fee->configured($newcomer));
        $this->assertTrue($fee->isFixed($newcomer));
        $this->assertSame(300.0, $fee->resolve($newcomer, 150.0), 'o valor informado é ignorado para novato');

        Customer::query()->delete();
        $this->returningStudent(document: '096.234.214-94', name: 'Pedro Lima');
        $returningLead = $this->lead(['student_name' => 'Pedro Lima', 'guardian_document' => '096.234.214-94']);
        $returning = app(PreEnrollmentConverter::class)->convert($returningLead, ['classroom_id' => $this->classroom2027->id, 'day_of_payment' => 10]);

        $this->assertFalse($fee->isFixed($returning));
        $this->assertSame(150.0, $fee->resolve($returning, 150.0));

        $legacy = Enrollment::query()->where('academic_year_id', $this->year2026->id)->firstOrFail();
        $this->assertNull($fee->configured($legacy), 'matrícula sem ficha não tem taxa configurada');
    }

    public function test_enrollment_fee_action_charges_the_configured_fee_for_a_newcomer(): void
    {
        $enrollment = app(PreEnrollmentConverter::class)->convert($this->lead(), ['classroom_id' => $this->classroom2027->id, 'day_of_payment' => 10]);

        $this->mock(ChargeOperations::class, fn ($mock) => $mock->shouldReceive('createInvoice')->once()->andReturnUsing(fn (\Closure $create) => $create()));
        $this->partialMock(InvoiceService::class, fn ($mock) => $mock->shouldReceive('createEnrollmentInvoiceFromEnrollment')->once()
            ->withArgs(fn (Enrollment $billed, float $amount): bool => $billed->is($enrollment) && $amount === 300.0)
            ->andReturn((new Invoice)->forceFill(['id' => 1])));

        Livewire::test(ViewEnrollment::class, ['record' => $enrollment->id])
            ->mountAction('createEnrollmentInvoice')
            ->assertActionDataSet(['enrollment_amount' => 300.0])
            ->setActionData(['enrollment_amount' => 10])
            ->callMountedAction()
            ->assertHasNoActionErrors();
    }

    public function test_generate_reenrollments_creates_leads_once(): void
    {
        [$student, $previous] = $this->returningStudent();

        Livewire::test(ListPreEnrollments::class)
            ->callAction('generateReenrollments', ['source_year_id' => $this->year2026->id, 'target_year_id' => $this->year2027->id])
            ->assertHasNoActionErrors();

        $lead = PreEnrollment::query()->sole();
        $this->assertSame(PreEnrollmentType::Reenrollment, $lead->type);
        $this->assertSame($student->id, (int) $lead->student_id);
        $this->assertSame($previous->id, (int) $lead->previous_enrollment_id);
        $this->assertSame($this->year2027->id, (int) $lead->academic_year_id);
        $this->assertNotNull($lead->access_token);

        Livewire::test(ListPreEnrollments::class)
            ->callAction('generateReenrollments', ['source_year_id' => $this->year2026->id, 'target_year_id' => $this->year2027->id])
            ->assertHasNoActionErrors();

        $this->assertSame(1, PreEnrollment::query()->count(), 'gerar de novo não duplica');
    }

    public function test_generate_reenrollments_rejects_a_target_year_before_the_source(): void
    {
        Livewire::test(ListPreEnrollments::class)
            ->callAction('generateReenrollments', ['source_year_id' => $this->year2027->id, 'target_year_id' => $this->year2026->id])
            ->assertHasActionErrors(['target_year_id']);

        $this->assertSame(0, PreEnrollment::query()->count());
    }

    public function test_type_filter_separates_newcomers_from_reenrollments(): void
    {
        $newcomer = $this->lead();
        $reenrollment = $this->lead(['student_name' => 'Pedro Lima', 'type' => PreEnrollmentType::Reenrollment]);

        Livewire::test(ListPreEnrollments::class)
            ->assertCanSeeTableRecords([$newcomer, $reenrollment])
            ->filterTable('type', PreEnrollmentType::Reenrollment->value)
            ->assertCanSeeTableRecords([$reenrollment])
            ->assertCanNotSeeTableRecords([$newcomer]);
    }

    public function test_reenrollment_link_actions_only_show_for_open_reenrollment_leads(): void
    {
        $newcomer = $this->lead();
        $reenrollment = $this->lead(['student_name' => 'Pedro Lima', 'type' => PreEnrollmentType::Reenrollment]);

        Livewire::test(ViewPreEnrollment::class, ['record' => $newcomer->id])
            ->assertActionHidden('sendReenrollmentLink')
            ->assertActionHidden('copyReenrollmentLink');

        if (! Route::has('pre-enrollment.reenrollment')) {
            $this->markTestIncomplete('A rota pública da rematrícula ainda não existe.');
        }

        Livewire::test(ViewPreEnrollment::class, ['record' => $reenrollment->id])
            ->assertActionVisible('sendReenrollmentLink')
            ->callAction('sendReenrollmentLink', ['message' => 'Olá! Link: x'])
            ->assertHasNoActionErrors();

        $reenrollment->refresh();
        $this->assertSame(PreEnrollmentStatus::InContact, $reenrollment->status);
        $this->assertNotNull($reenrollment->access_token, 'o link é criado ao montar a mensagem');
    }

    public function test_panel_created_lead_for_a_returning_student_is_classified_as_reenrollment(): void
    {
        [$student, $previous] = $this->returningStudent();

        Livewire::test(CreatePreEnrollment::class)
            ->fillForm([
                'academic_year_id' => $this->year2027->id,
                'student_name' => 'Lucas Andrade',
                'student_birth_date' => '2019-05-20',
                'guardian_name' => 'Carla Andrade',
                'guardian_document' => '529.982.247-25',
                'guardian_phone' => '(83) 99876-5432',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $lead = PreEnrollment::query()->sole();
        $this->assertSame(PreEnrollmentType::Reenrollment, $lead->type);
        $this->assertSame($previous->id, (int) $lead->previous_enrollment_id);
    }

    public function test_converting_rejects_classroom_or_plan_from_another_year(): void
    {
        $lead = $this->lead();

        $this->assertConversionFails($lead, ['classroom_id' => $this->classroom2026->id, 'plan_id' => $this->plan2026->id], 'classroom_id');
        $this->assertConversionFails($lead, ['classroom_id' => $this->classroom2027->id, 'plan_id' => $this->plan2026->id], 'plan_id');

        $this->assertSame(0, Enrollment::query()->count());
        $this->assertSame(PreEnrollmentStatus::New, $lead->refresh()->status);
    }

    public function test_legacy_classroom_without_year_is_only_accepted_for_the_default_year(): void
    {
        $legacyPlan = $this->plan('Mensal antigo', 500, null);
        $legacyClassroom = $this->classroom('Turma antiga', null, $legacyPlan);

        $this->assertConversionFails($this->lead(), ['classroom_id' => $legacyClassroom->id, 'plan_id' => $legacyPlan->id], 'classroom_id');

        $lead2026 = $this->lead(['academic_year_id' => $this->year2026->id, 'student_name' => 'Pedro Lima', 'admission_offer_id' => null]);
        $enrollment = app(PreEnrollmentConverter::class)->convert($lead2026, ['classroom_id' => $legacyClassroom->id, 'plan_id' => $legacyPlan->id, 'day_of_payment' => 10]);

        $this->assertSame($this->year2026->id, (int) $enrollment->academic_year_id);
    }

    public function test_converting_twice_fails(): void
    {
        $lead = $this->lead();
        app(PreEnrollmentConverter::class)->convert($lead, ['classroom_id' => $this->classroom2027->id, 'day_of_payment' => 10]);

        $this->assertConversionFails($lead->refresh(), ['classroom_id' => $this->classroom2027->id], 'pre_enrollment');
        $this->assertSame(1, Enrollment::query()->count());
    }

    public function test_student_already_enrolled_in_the_year_blocks_conversion_and_keeps_the_lead_open(): void
    {
        $customer = Customer::create(['name' => 'Carla Andrade', 'document' => '529.982.247-25']);
        $student = Student::create(['name' => 'Lucas Andrade', 'customer_id' => $customer->id, 'date_of_birth' => '2019-05-20', 'gender' => 'M']);
        Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $this->classroom2027->id, 'academic_year_id' => $this->year2027->id,
            'enrollment_date' => '2026-10-01', 'status' => EnrollmentStatus::Reserved->value,
        ]);
        $lead = $this->lead();

        try {
            app(PreEnrollmentConverter::class)->convert($lead, ['classroom_id' => $this->classroom2027->id, 'day_of_payment' => 10]);
            $this->fail('A conversão deveria ser recusada.');
        } catch (ValidationException $exception) {
            $this->assertContains(Enrollment::DUPLICATE_IN_YEAR_MESSAGE, $exception->errors()['student_id']);
        }

        $this->assertSame(1, Enrollment::query()->count());
        $this->assertSame(PreEnrollmentStatus::New, $lead->refresh()->status);
        $this->assertNull($lead->enrollment_id);

        Livewire::test(ViewPreEnrollment::class, ['record' => $lead->id])
            ->assertSee('Aluno já matriculado neste ano');
    }

    public function test_convert_action_asks_for_the_year_classrooms_when_none_exist(): void
    {
        $year2028 = AcademicYear::create(['description' => 'Ano letivo 2028', 'year' => 2028, 'admissions_open' => true]);
        $lead = $this->lead(['academic_year_id' => $year2028->id, 'admission_offer_id' => null]);

        Livewire::test(ViewPreEnrollment::class, ['record' => $lead->id])
            ->mountAction('convert')
            ->assertMountedActionModalSee('Cadastre as turmas de Ano letivo 2028 antes de converter');
    }

    public function test_offer_with_a_plan_from_another_year_cannot_be_saved(): void
    {
        $this->expectException(ValidationException::class);

        AdmissionOffer::create([
            'academic_year_id' => $this->year2027->id, 'plan_id' => $this->plan2026->id, 'name' => '4º ano',
        ]);
    }

    public function test_registering_a_contact_updates_last_contact_and_status(): void
    {
        $lead = $this->lead();

        Livewire::test(ViewPreEnrollment::class, ['record' => $lead->id])
            ->callAction('registerContact', ['channel' => 'phone', 'status_after' => PreEnrollmentStatus::VisitScheduled->value, 'notes' => 'Visita na sexta'])
            ->assertHasNoActionErrors();

        $lead->refresh();
        $this->assertSame(PreEnrollmentStatus::VisitScheduled, $lead->status);
        $this->assertNotNull($lead->last_contact_at);
        $this->assertSame('Visita na sexta', $lead->contacts()->sole()->notes);
    }

    public function test_marking_as_lost_requires_a_reason(): void
    {
        $lead = $this->lead();

        Livewire::test(ViewPreEnrollment::class, ['record' => $lead->id])
            ->callAction('markLost', ['lost_reason' => ''])
            ->assertHasActionErrors(['lost_reason' => 'required']);

        $this->assertSame(PreEnrollmentStatus::New, $lead->refresh()->status);

        Livewire::test(ViewPreEnrollment::class, ['record' => $lead->id])
            ->callAction('markLost', ['lost_reason' => 'Escolheu outra escola'])
            ->assertHasNoActionErrors();

        $this->assertSame(PreEnrollmentStatus::Lost, $lead->refresh()->status);
        $this->assertSame('Escolheu outra escola', $lead->lost_reason);
    }

    public function test_whatsapp_action_registers_the_contact_and_moves_new_lead_to_in_contact(): void
    {
        $lead = $this->lead();

        Livewire::test(ViewPreEnrollment::class, ['record' => $lead->id])
            ->callAction('whatsapp', ['message' => 'Olá, Carla!'])
            ->assertHasNoActionErrors();

        $this->assertSame(PreEnrollmentStatus::InContact, $lead->refresh()->status);
        $this->assertSame('whatsapp', $lead->contacts()->sole()->channel->value);
    }

    public function test_academic_year_page_lists_offers_and_only_offers_plans_of_the_same_year(): void
    {
        Livewire::test(AdmissionOffersRelationManager::class, ['ownerRecord' => $this->year2027, 'pageClass' => EditAcademicYear::class])
            ->assertOk()
            ->assertCanSeeTableRecords([$this->offer2027])
            ->mountTableAction('create')
            ->assertFormFieldExists('plan_id', fn ($field): bool => array_keys($field->getOptions()) === [$this->plan2027->id]);

        $this->get(AcademicYearResource::getUrl('edit', ['record' => $this->year2027]))->assertOk()->assertSee('Pré-matrícula');
    }

    public function test_panel_pages_render(): void
    {
        $lead = $this->lead();

        $this->get(PreEnrollmentResource::getUrl('index'))->assertOk()->assertSee('Lucas Andrade');
        $this->get(PreEnrollmentResource::getUrl('view', ['record' => $lead]))->assertOk()->assertSee('Converter em matrícula');
        $this->get(PreEnrollmentResource::getUrl('edit', ['record' => $lead]))->assertOk();
        $this->get(PreEnrollmentResource::getUrl('create'))->assertOk();
        $this->get(EnrollmentResource::getUrl('create'))->assertOk();
        $this->get(ClassroomResource::getUrl('create', ['academic_year_id' => $this->year2027->id]))->assertOk();
        $this->get(ClassroomResource::getUrl('edit', ['record' => $this->classroom2027]))->assertOk()->assertSee('Mensal 2027');

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin)->get(PlanResource::getUrl('create'))->assertOk()->assertSee('Ano letivo');
        $this->actingAs($admin)->get(PlanResource::getUrl('index'))->assertOk()->assertSee('Mensal 2027');
    }

    public function test_teacher_cannot_access_pre_enrollments(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');
        Teacher::create(['name' => 'Professor', 'email' => 'prof@example.test', 'user_id' => $teacher->id]);
        $lead = $this->lead();

        $this->actingAs($teacher)->get(PreEnrollmentResource::getUrl('index'))->assertForbidden();
        $this->actingAs($teacher)->get(PreEnrollmentResource::getUrl('view', ['record' => $lead]))->assertForbidden();
    }

    private function assertConversionFails(PreEnrollment $lead, array $data, string $field): void
    {
        try {
            app(PreEnrollmentConverter::class)->convert($lead, [...$data, 'day_of_payment' => 10]);
            $this->fail("A conversão deveria falhar em {$field}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    /**
     * Aluno com matrícula ativa em 2026 (veterano para 2027).
     *
     * @return array{0: Student, 1: Enrollment}
     */
    private function returningStudent(string $document = '529.982.247-25', string $name = 'Lucas Andrade'): array
    {
        $customer = Customer::query()->firstOrCreate(['document' => $document], ['name' => 'Carla Andrade']);
        $student = Student::create(['name' => $name, 'customer_id' => $customer->id, 'date_of_birth' => '2019-05-20', 'gender' => 'M']);
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'customer_id' => $customer->id, 'classroom_id' => $this->classroom2026->id,
            'academic_year_id' => $this->year2026->id, 'plan_id' => $this->plan2026->id,
            'enrollment_date' => '2026-02-01', 'status' => EnrollmentStatus::Active->value,
        ]);

        return [$student, $enrollment];
    }

    private function lead(array $attributes = []): PreEnrollment
    {
        return PreEnrollment::create([
            'academic_year_id' => $this->year2027->id,
            'admission_offer_id' => $this->offer2027->id,
            'status' => PreEnrollmentStatus::New,
            'student_name' => 'Lucas Andrade',
            'student_birth_date' => '2019-05-20',
            'guardian_name' => 'Carla Andrade',
            'guardian_document' => '529.982.247-25',
            'guardian_phone' => '(83) 99876-5432',
            ...$attributes,
        ]);
    }

    private function plan(string $name, float $amount, ?AcademicYear $year): Plan
    {
        return Plan::create([
            'academic_year_id' => $year?->id, 'name' => $name, 'base_amount' => $amount, 'has_discount' => false,
            'discount_type' => 'none', 'discount_value' => 0, 'final_value' => $amount, 'is_active' => true,
        ]);
    }

    private function classroom(string $name, ?AcademicYear $year, Plan $plan): Classroom
    {
        $classroom = Classroom::create(['name' => $name, 'academic_year_id' => $year?->id, 'level_id' => $this->level->id]);
        $classroom->plans()->attach($plan);

        return $classroom;
    }
}
