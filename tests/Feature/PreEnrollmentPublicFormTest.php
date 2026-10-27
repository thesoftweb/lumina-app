<?php

namespace Tests\Feature;

use App\Enums\PreEnrollmentStatus;
use App\Http\Controllers\PreEnrollmentController;
use App\Models\AcademicYear;
use App\Models\AdmissionOffer;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Level;
use App\Models\Plan;
use App\Models\PreEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Ficha pública de pré-matrícula: separada por ano letivo, com os valores do ano e sem leads duplicados. */
class PreEnrollmentPublicFormTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year2026;

    private AcademicYear $year2027;

    private AdmissionOffer $offer2026;

    private AdmissionOffer $offer2027;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 27)->setTime(10, 0));

        Company::create(['name' => 'Escola Ceci', 'email' => 'escola@example.test', 'phone' => '(83) 3333-4444']);
        $level = Level::create(['name' => 'Fundamental I']);

        $this->year2026 = AcademicYear::create(['description' => 'Ano letivo 2026', 'year' => 2026, 'is_default' => true]);
        $this->year2027 = AcademicYear::create([
            'description' => 'Ano letivo 2027', 'year' => 2027, 'is_default' => false,
            'admissions_open' => true, 'admissions_starts_at' => '2026-10-01', 'admissions_ends_at' => '2027-01-31',
            'admissions_notes' => 'Traga a certidão de nascimento.',
        ]);

        $this->offer2026 = AdmissionOffer::create([
            'academic_year_id' => $this->year2026->id, 'level_id' => $level->id, 'name' => 'Oferta antiga 2026',
            'plan_id' => $this->plan($this->year2026, 'Plano 2026', 650)->id, 'is_active' => true,
        ]);
        $this->offer2027 = AdmissionOffer::create([
            'academic_year_id' => $this->year2027->id, 'level_id' => $level->id, 'name' => '3º ano - Fundamental I',
            'shift' => 'morning', 'plan_id' => $this->plan($this->year2027, 'Plano 2027', 890, 801)->id,
            'enrollment_fee' => 350, 'vacancies' => 20, 'is_active' => true,
        ]);
    }

    public function test_form_shows_only_offers_and_prices_of_its_year(): void
    {
        $this->get('/pre-matricula/2027')
            ->assertOk()
            ->assertSee('Valores 2027')
            ->assertSee('3º ano - Fundamental I')
            ->assertSee('Manhã')
            ->assertSee('890,00')
            // Valor fixo: o site nunca divulga desconto, mesmo que o plano tenha um configurado
            ->assertDontSee('801,00')
            ->assertDontSee('desconto')
            ->assertSee('350,00')
            ->assertSee('Traga a certidão de nascimento.')
            ->assertDontSee('Oferta antiga 2026')
            ->assertDontSee('650,00');
    }

    public function test_closed_year_shows_closed_page_and_rejects_submissions(): void
    {
        $this->get('/pre-matricula/2026')
            ->assertOk()
            ->assertSee('não estão abertas')
            ->assertDontSee('Enviar pré-matrícula');

        $this->post('/pre-matricula/2026', $this->payload(['admission_offer_id' => $this->offer2026->id]))
            ->assertRedirect(route('pre-enrollment.create', ['year' => 2026]));

        $this->assertDatabaseCount('pre_enrollments', 0);
    }

    public function test_unknown_year_is_not_found(): void
    {
        $this->get('/pre-matricula/2030')->assertNotFound();
    }

    public function test_valid_submission_creates_a_lead_in_the_year_of_the_link(): void
    {
        $this->get('/pre-matricula/2027?utm_source=instagram&utm_campaign=matriculas2027')
            ->assertSee('name="utm_source" value="instagram"', false);

        $this->post('/pre-matricula/2027', $this->payload(['utm_source' => 'instagram', 'utm_campaign' => 'matriculas2027']))
            ->assertRedirect(route('pre-enrollment.done', ['year' => 2027]))
            ->assertSessionHas(PreEnrollmentController::CODE_SESSION_KEY);

        $lead = PreEnrollment::sole();
        $this->assertSame($this->year2027->id, $lead->academic_year_id);
        $this->assertSame($this->offer2027->id, $lead->admission_offer_id);
        $this->assertSame(PreEnrollmentStatus::New, $lead->status);
        $this->assertSame('09623421494', $lead->guardian_document);
        $this->assertSame('83999998888', $lead->guardian_phone);
        $this->assertNotNull($lead->consent_at);
        $this->assertSame('instagram', $lead->utm_source);
        $this->assertSame('matriculas2027', $lead->utm_campaign);
        $this->assertStringStartsWith('2027-', $lead->code);
    }

    public function test_invalid_cpf_and_missing_consent_are_rejected(): void
    {
        $this->from('/pre-matricula/2027')
            ->post('/pre-matricula/2027', $this->payload(['guardian_document' => '111.111.111-11', 'consent' => null]))
            ->assertRedirect('/pre-matricula/2027')
            ->assertSessionHasErrors(['guardian_document', 'consent']);

        $this->assertDatabaseCount('pre_enrollments', 0);
    }

    public function test_errors_are_summarized_and_answers_kept(): void
    {
        $this->followingRedirects()->from('/pre-matricula/2027')
            ->post('/pre-matricula/2027', $this->payload(['guardian_document' => '123', 'student_name' => 'Pedro Henrique']))
            ->assertOk()
            ->assertSee('Confira o campo abaixo')
            ->assertSee('href="#campo-guardian-document"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('value="Pedro Henrique"', false);
    }

    public function test_full_offer_shows_waiting_list_but_stays_selectable(): void
    {
        $this->offer2027->update(['vacancies' => 1]);
        $this->post('/pre-matricula/2027', $this->payload())->assertSessionHasNoErrors();

        $this->get('/pre-matricula/2027')->assertSee('Lista de espera')
            ->assertSee('name="admission_offer_id" value="'.$this->offer2027->id.'"', false);
    }

    public function test_offer_from_another_year_is_rejected(): void
    {
        $this->post('/pre-matricula/2027', $this->payload(['admission_offer_id' => $this->offer2026->id]))
            ->assertSessionHasErrors('admission_offer_id');

        $this->assertDatabaseCount('pre_enrollments', 0);
    }

    public function test_offer_is_required_when_the_year_has_offers(): void
    {
        $this->post('/pre-matricula/2027', $this->payload(['admission_offer_id' => null]))
            ->assertSessionHasErrors('admission_offer_id');
    }

    public function test_resubmitting_the_same_child_in_the_same_year_updates_the_lead(): void
    {
        $this->post('/pre-matricula/2027', $this->payload(['message' => 'Primeira mensagem']));
        $this->post('/pre-matricula/2027', $this->payload([
            'student_name' => '  ana clara  de souza ', 'guardian_phone' => '(83) 98888-7777', 'message' => 'Segunda mensagem',
        ]));

        $lead = PreEnrollment::sole();
        $this->assertSame('83988887777', $lead->guardian_phone);
        $this->assertStringContainsString('Primeira mensagem', $lead->message);
        $this->assertStringContainsString('Segunda mensagem', $lead->message);
    }

    public function test_same_child_in_another_year_is_a_separate_lead(): void
    {
        $this->year2026->update(['admissions_open' => true, 'admissions_starts_at' => null, 'admissions_ends_at' => null]);

        $this->post('/pre-matricula/2026', $this->payload(['admission_offer_id' => $this->offer2026->id]))->assertSessionHasNoErrors();
        $this->post('/pre-matricula/2027', $this->payload())->assertSessionHasNoErrors();

        $this->assertSame(1, PreEnrollment::query()->forYear($this->year2026)->count());
        $this->assertSame(1, PreEnrollment::query()->forYear($this->year2027)->count());
    }

    public function test_honeypot_pretends_success_without_saving(): void
    {
        $this->post('/pre-matricula/2027', $this->payload(['website' => 'http://spam.example']))
            ->assertRedirect(route('pre-enrollment.done', ['year' => 2027]));

        $this->assertDatabaseCount('pre_enrollments', 0);
    }

    public function test_done_page_shows_the_code_only_from_the_session(): void
    {
        $this->get('/pre-matricula/2027/enviada')->assertRedirect(route('pre-enrollment.create', ['year' => 2027]));

        $this->post('/pre-matricula/2027', $this->payload())->assertRedirect();
        $code = PreEnrollment::sole()->code;

        $this->get('/pre-matricula/2027/enviada')->assertOk()->assertSee($code)->assertSee('Próximos passos');
        $this->get('/pre-matricula/2027/enviada')->assertRedirect(route('pre-enrollment.create', ['year' => 2027]));
    }

    public function test_index_redirects_to_the_only_open_year_keeping_campaign(): void
    {
        $this->get('/pre-matricula?utm_source=google')
            ->assertRedirect(route('pre-enrollment.create', ['year' => 2027, 'utm_source' => 'google']));
    }

    public function test_index_lists_years_when_several_are_open_and_explains_when_none(): void
    {
        $this->year2026->update(['admissions_open' => true]);
        $this->get('/pre-matricula')->assertOk()->assertSee('Ano letivo 2026')->assertSee('Ano letivo 2027');

        AcademicYear::query()->update(['admissions_open' => false]);
        $this->get('/pre-matricula')->assertOk()->assertSee('não há inscrições abertas')->assertSee('Ligar para a secretaria');
    }

    public function test_existing_customer_with_masked_cpf_is_matched(): void
    {
        $customer = Customer::create(['name' => 'Maria de Souza', 'document' => '096.234.214-94']);

        $this->post('/pre-matricula/2027', $this->payload())->assertSessionHasNoErrors();

        $this->assertSame($customer->id, PreEnrollment::sole()->customer_id);
    }

    private function plan(AcademicYear $year, string $name, float $base, ?float $final = null): Plan
    {
        return Plan::create([
            'academic_year_id' => $year->id, 'name' => $name, 'base_amount' => $base,
            'has_discount' => $final !== null, 'discount_type' => $final !== null ? 'fixed' : 'none',
            'discount_value' => $final !== null ? $base - $final : 0, 'final_value' => $final ?? $base, 'is_active' => true,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'admission_offer_id' => $this->offer2027->id,
            'student_name' => 'Ana Clara de Souza',
            'student_birth_date' => '2018-05-10',
            'student_gender' => 'F',
            'guardian_name' => 'Maria de Souza',
            'guardian_document' => '096.234.214-94',
            'guardian_kinship' => 'mãe',
            'guardian_email' => 'maria@example.test',
            'guardian_phone' => '(83) 99999-8888',
            'postal_code' => '58300-000',
            'city_name' => 'Santa Rita/PB',
            'source' => 'instagram',
            'consent' => '1',
        ], $overrides);
    }
}
