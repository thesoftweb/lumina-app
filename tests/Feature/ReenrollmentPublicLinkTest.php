<?php

namespace Tests\Feature;

use App\Enums\PreEnrollmentStatus;
use App\Enums\PreEnrollmentType;
use App\Models\AcademicYear;
use App\Models\AdmissionOffer;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\Plan;
use App\Models\PreEnrollment;
use App\Models\Student;
use App\Services\Admissions\ReenrollmentCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Rematrícula pelo fluxo da pré-matrícula: geração das fichas, link da família e detecção na ficha pública. */
class ReenrollmentPublicLinkTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year2026;

    private AcademicYear $year2027;

    private AdmissionOffer $offer2027;

    private Classroom $classroom2026;

    private Customer $customer;

    private Student $student;

    private Enrollment $enrollment2026;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 27)->setTime(10, 0));

        Company::create(['name' => 'Escola Ceci', 'email' => 'escola@example.test', 'phone' => '(83) 3333-4444']);
        $level = Level::create(['name' => 'Fundamental I']);

        $this->year2026 = AcademicYear::create(['description' => 'Ano letivo 2026', 'year' => 2026, 'is_default' => true, 'end_at' => '2026-12-18']);
        $this->year2027 = AcademicYear::create([
            'description' => 'Ano letivo 2027', 'year' => 2027, 'is_default' => false, 'end_at' => '2027-12-17',
            'admissions_open' => true, 'admissions_starts_at' => '2026-10-01', 'admissions_ends_at' => '2027-01-31',
        ]);

        $plan = Plan::create([
            'academic_year_id' => $this->year2027->id, 'name' => 'Plano 2027', 'base_amount' => 890,
            'has_discount' => true, 'discount_type' => 'fixed', 'discount_value' => 89, 'final_value' => 801, 'is_active' => true,
        ]);
        $this->offer2027 = AdmissionOffer::create([
            'academic_year_id' => $this->year2027->id, 'level_id' => $level->id, 'name' => '4º ano - Fundamental I',
            'plan_id' => $plan->id, 'enrollment_fee' => 350, 'is_active' => true,
        ]);

        $this->classroom2026 = Classroom::create(['name' => '3º A', 'level_id' => $level->id]);
        $this->customer = Customer::create([
            'name' => 'Maria de Souza', 'document' => '096.234.214-94', 'phone' => '83999998888', 'email' => 'maria@example.test',
        ]);
        $this->student = Student::create([
            'name' => 'Ana Clara de Souza', 'date_of_birth' => '2018-05-10', 'gender' => 'F', 'customer_id' => $this->customer->id,
        ]);
        $this->enrollment2026 = $this->enroll($this->student, $this->year2026);
    }

    public function test_campaign_creates_reenrollment_leads_once(): void
    {
        $campaign = app(ReenrollmentCampaign::class);

        $created = $campaign->generate($this->year2026, $this->year2027);

        $this->assertCount(1, $created);
        $lead = $created->first();
        $this->assertSame(PreEnrollmentType::Reenrollment, $lead->type);
        $this->assertSame(PreEnrollmentStatus::New, $lead->status);
        $this->assertSame($this->year2027->id, $lead->academic_year_id);
        $this->assertSame($this->enrollment2026->id, $lead->previous_enrollment_id);
        $this->assertSame($this->student->id, $lead->student_id);
        $this->assertSame('09623421494', $lead->guardian_document);
        $this->assertNotEmpty($lead->access_token);

        $this->assertCount(0, $campaign->generate($this->year2026, $this->year2027), 'rodar de novo não duplica');
    }

    public function test_campaign_skips_enrolled_canceled_and_already_listed_students(): void
    {
        $enrolledIn2027 = $this->newStudent('Bruno Lima', '012.345.678-90');
        $this->enroll($enrolledIn2027, $this->year2026);
        $this->enroll($enrolledIn2027, $this->year2027, 'reserved');

        $canceled = $this->newStudent('Carla Dias', '111.444.777-35');
        $this->enroll($canceled, $this->year2026, 'canceled');

        $withLead = $this->newStudent('Davi Melo', '529.982.247-25');
        $this->enroll($withLead, $this->year2026);
        PreEnrollment::create([
            'academic_year_id' => $this->year2027->id, 'student_id' => $withLead->id, 'status' => PreEnrollmentStatus::Lost,
            'student_name' => 'Davi Melo', 'student_birth_date' => '2018-01-01', 'guardian_name' => 'Resp Davi',
            'guardian_document' => '52998224725', 'guardian_phone' => '83988887777',
        ]);

        $created = app(ReenrollmentCampaign::class)->generate($this->year2026, $this->year2027);

        $this->assertSame([$this->student->id], $created->pluck('student_id')->all());
    }

    public function test_campaign_lists_students_without_guardian_cpf_as_incomplete(): void
    {
        // Data de nascimento e responsável são obrigatórios no banco; o que pode faltar é o CPF do responsável
        $noCpf = Student::create(['name' => 'Fabio Reis', 'date_of_birth' => '2018-02-02', 'gender' => 'M',
            'customer_id' => Customer::create(['name' => 'Resp Fabio', 'document' => ''])->id]);
        $this->enroll($noCpf, $this->year2026);

        $campaign = app(ReenrollmentCampaign::class);

        $this->assertEqualsCanonicalizing([$noCpf->id], $campaign->incomplete($this->year2026, $this->year2027)->pluck('student_id')->all());
        $this->assertSame([$this->student->id], $campaign->generate($this->year2026, $this->year2027)->pluck('student_id')->all());
    }

    public function test_link_page_shows_child_and_masked_cpf_without_discount(): void
    {
        $lead = $this->lead();

        $this->get($lead->reenrollmentUrl())
            ->assertOk()
            ->assertSee('Rematrícula 2027')
            ->assertSee('Olá, Maria!')
            ->assertSee('Ana Clara de Souza')
            ->assertSee('3º A')
            ->assertSee('***.234.214-**')
            ->assertDontSee('096.234.214-94')
            ->assertDontSee('09623421494')
            ->assertSee('890,00')
            ->assertSee('350,00')
            ->assertDontSee('801,00')
            ->assertDontSee('desconto');
    }

    public function test_wrong_token_or_other_year_returns_404(): void
    {
        $lead = $this->lead();

        $this->get('/pre-matricula/2027/rematricula/'.str_repeat('x', 40))->assertNotFound();
        $this->get('/pre-matricula/2026/rematricula/'.$lead->access_token)->assertNotFound();
        $this->post('/pre-matricula/2026/rematricula/'.$lead->access_token, $this->payload())->assertNotFound();
    }

    public function test_new_student_lead_has_no_reenrollment_link(): void
    {
        $lead = PreEnrollment::create([
            'academic_year_id' => $this->year2027->id, 'type' => PreEnrollmentType::NewStudent, 'access_token' => str_repeat('a', 40),
            'student_name' => 'Gael Rocha', 'student_birth_date' => '2019-01-01', 'guardian_name' => 'Resp Gael',
            'guardian_document' => '39053344705', 'guardian_phone' => '83977776666',
        ]);

        $this->get('/pre-matricula/2027/rematricula/'.$lead->access_token)->assertNotFound();
    }

    public function test_family_confirms_reenrollment_updating_contact_and_offer(): void
    {
        $lead = $this->lead();

        $this->post($lead->reenrollmentUrl(), $this->payload(['guardian_phone' => '(83) 98765-4321', 'message' => 'Tudo certo.']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('pre-enrollment.done', ['year' => 2027]));

        $lead->refresh();
        $this->assertSame('83987654321', $lead->guardian_phone);
        $this->assertSame($this->offer2027->id, $lead->admission_offer_id);
        $this->assertNotNull($lead->responded_at);
        $this->assertNotNull($lead->consent_at);
        $this->assertSame(PreEnrollmentStatus::New, $lead->status);
        $this->assertSame('Tudo certo.', $lead->message);
        $this->assertSame('Ana Clara de Souza', $lead->student_name, 'dados da criança não mudam pelo link');

        $this->get(route('pre-enrollment.done', ['year' => 2027]))->assertOk();
    }

    public function test_confirmation_page_mentions_the_child(): void
    {
        $lead = $this->lead();

        $this->followingRedirects()
            ->post($lead->reenrollmentUrl(), $this->payload())
            ->assertOk()
            ->assertSee('Rematrícula confirmada!')
            ->assertSee('rematrícula de Ana')
            ->assertSee($lead->code);
    }

    public function test_confirmation_requires_consent_and_an_offer_of_the_year(): void
    {
        $lead = $this->lead();
        $offer2026 = AdmissionOffer::create(['academic_year_id' => $this->year2026->id, 'name' => 'Oferta 2026', 'is_active' => true]);

        $this->post($lead->reenrollmentUrl(), $this->payload(['consent' => null, 'admission_offer_id' => $offer2026->id]))
            ->assertSessionHasErrors(['consent', 'admission_offer_id']);

        $this->assertNull($lead->refresh()->responded_at);
    }

    public function test_converted_or_lost_lead_shows_inactive_page_and_rejects_post(): void
    {
        $lead = $this->lead();
        $url = $lead->reenrollmentUrl();

        foreach ([PreEnrollmentStatus::Converted, PreEnrollmentStatus::Lost] as $status) {
            $lead->forceFill(['status' => $status])->save();

            $this->get($url)->assertOk()->assertSee('não está mais ativo')->assertDontSee('Ana Clara de Souza');
            $this->post($url, $this->payload(['guardian_phone' => '83911112222']))->assertOk()->assertSee('não está mais ativo');
            $this->assertNotSame('83911112222', $lead->refresh()->guardian_phone);
        }
    }

    public function test_public_form_detects_returning_student_as_reenrollment(): void
    {
        $this->post('/pre-matricula/2027', $this->publicPayload())->assertSessionHasNoErrors();

        $lead = PreEnrollment::sole();
        $this->assertSame(PreEnrollmentType::Reenrollment, $lead->type);
        $this->assertSame($this->enrollment2026->id, $lead->previous_enrollment_id);
        $this->assertSame($this->student->id, $lead->student_id);
    }

    public function test_public_form_keeps_registered_student_without_enrollment_as_new(): void
    {
        $sibling = Student::create(['name' => 'Bia de Souza', 'date_of_birth' => '2020-03-03', 'gender' => 'F', 'customer_id' => $this->customer->id]);

        $this->post('/pre-matricula/2027', $this->publicPayload(['student_name' => 'Bia de Souza', 'student_birth_date' => '2020-03-03']))
            ->assertSessionHasNoErrors();

        $lead = PreEnrollment::sole();
        $this->assertSame(PreEnrollmentType::NewStudent, $lead->type);
        $this->assertSame($sibling->id, $lead->student_id);
        $this->assertNull($lead->previous_enrollment_id);
    }

    public function test_public_form_points_returning_families_to_the_reenrollment_link(): void
    {
        $this->get('/pre-matricula/2027')->assertOk()->assertSee('Já é aluno?')->assertDontSee('desconto');
    }

    private function lead(): PreEnrollment
    {
        return app(ReenrollmentCampaign::class)->generate($this->year2026, $this->year2027)->sole();
    }

    private function enroll(Student $student, AcademicYear $year, string $status = 'active'): Enrollment
    {
        return Enrollment::create([
            'student_id' => $student->id, 'customer_id' => $student->customer_id, 'classroom_id' => $this->classroom2026->id,
            'academic_year_id' => $year->id, 'enrollment_date' => '2026-02-01', 'status' => $status,
        ]);
    }

    private function newStudent(string $name, string $cpf): Student
    {
        $customer = Customer::create(['name' => 'Resp '.$name, 'document' => $cpf]);

        return Student::create(['name' => $name, 'date_of_birth' => '2018-01-01', 'gender' => 'M', 'customer_id' => $customer->id]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'admission_offer_id' => $this->offer2027->id,
            'guardian_phone' => '(83) 99999-8888',
            'guardian_email' => 'maria@example.test',
            'postal_code' => '58300-000',
            'city_name' => 'Santa Rita/PB',
            'consent' => '1',
        ], $overrides);
    }

    private function publicPayload(array $overrides = []): array
    {
        return array_merge([
            'admission_offer_id' => $this->offer2027->id,
            'student_name' => 'Ana Clara de Souza',
            'student_birth_date' => '2018-05-10',
            'guardian_name' => 'Maria de Souza',
            'guardian_document' => '096.234.214-94',
            'guardian_phone' => '(83) 99999-8888',
            'consent' => '1',
        ], $overrides);
    }
}
