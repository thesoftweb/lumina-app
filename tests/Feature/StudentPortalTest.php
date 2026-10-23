<?php

namespace Tests\Feature;

use App\Models\Classroom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StudentPortalTest extends TestCase
{
    use RefreshDatabase;

    private const DOCUMENT = '12345678901';

    public function test_guardian_signs_in_with_document_and_browses_the_portal(): void
    {
        $this->familyWithInvoice(self::DOCUMENT);

        $this->post('/portal/access', ['document' => self::DOCUMENT])->assertRedirect(route('portal.show'));

        $this->get('/portal/aluno')->assertOk()->assertSee('Matrículas');
        $this->get('/portal/matriculas')->assertOk()->assertSee('Aluno Portal')->assertSee('1º A');
        $this->get('/portal/faturas')->assertOk()->assertSee('Mensalidade');
        $this->get('/portal/events')->assertOk();
        $this->get('/portal/student')->assertRedirect('/portal/aluno');
    }

    public function test_unknown_document_is_rejected(): void
    {
        $this->post('/portal/access', ['document' => '99999999999'])->assertSessionHasErrors('document');
        $this->get('/portal/aluno')->assertRedirect(route('portal.login'));
    }

    public function test_pix_syncs_the_customer_with_the_right_state_and_returns_the_qr_code(): void
    {
        $invoiceId = $this->familyWithInvoice(self::DOCUMENT);
        $this->fakeAsaas();

        $this->withSession(['customer_document' => self::DOCUMENT])
            ->getJson(route('portal.invoices.pix-qrcode', $invoiceId))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payload', 'pix-payload');

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/customers')
            && $request['addressCity'] === 'Santa Rita'
            && $request['addressState'] === 'PB');
    }

    public function test_pix_requires_a_portal_session(): void
    {
        $invoiceId = $this->familyWithInvoice(self::DOCUMENT);
        $this->fakeAsaas();

        $this->getJson(route('portal.invoices.pix-qrcode', $invoiceId))
            ->assertUnauthorized()
            ->assertJsonPath('success', false);

        Http::assertNothingSent();
    }

    public function test_pix_of_another_family_is_not_found(): void
    {
        $this->familyWithInvoice(self::DOCUMENT);
        $otherInvoiceId = $this->familyWithInvoice('10987654321', email: 'outra@example.test');
        $this->fakeAsaas();

        $this->withSession(['customer_document' => self::DOCUMENT])
            ->getJson(route('portal.invoices.pix-qrcode', $otherInvoiceId))
            ->assertNotFound()
            ->assertJsonPath('success', false);

        Http::assertNothingSent();
    }

    /** Responsável com endereço em Santa Rita/PB, um aluno matriculado e uma fatura em aberto. */
    private function familyWithInvoice(string $document, string $email = 'resp@example.test'): int
    {
        $now = ['created_at' => now(), 'updated_at' => now()];

        $levelId = DB::table('levels')->value('id') ?? DB::table('levels')->insertGetId(['name' => 'Fundamental'] + $now);
        $classroom = Classroom::firstOrCreate(['name' => '1º A'], ['level_id' => $levelId]);

        if (! DB::table('states')->where('code', 25)->exists()) {
            DB::table('states')->insert(['code' => 25, 'name' => 'Paraíba', 'abbreviation' => 'PB'] + $now);
        }
        $cityId = DB::table('cities')->where('code', 2513703)->value('id')
            ?? DB::table('cities')->insertGetId(['code' => 2513703, 'name' => 'Santa Rita', 'state_id' => 25] + $now);

        $customerId = DB::table('customers')->insertGetId([
            'name' => 'Responsável', 'email' => $email, 'document' => $document,
            'address' => 'Rua A', 'address_number' => '10', 'city_id' => $cityId, 'postal_code' => '58300-000',
        ] + $now);
        $studentId = DB::table('students')->insertGetId([
            'customer_id' => $customerId, 'name' => 'Aluno Portal', 'date_of_birth' => '2015-01-01',
        ] + $now);
        DB::table('enrollments')->insert([
            'student_id' => $studentId, 'customer_id' => $customerId, 'classroom_id' => $classroom->id,
            'enrollment_date' => now()->toDateString(), 'status' => 'active',
        ] + $now);

        return DB::table('invoices')->insertGetId([
            'customer_id' => $customerId, 'reference' => 'Mensalidade', 'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(), 'amount' => 500, 'status' => 'open',
        ] + $now);
    }

    private function fakeAsaas(): void
    {
        config(['asaas.api_key' => 'test-key']);

        Http::fake(fn (Request $request) => match (true) {
            str_contains($request->url(), '/pixQrCode') => Http::response([
                'encodedImage' => 'img', 'payload' => 'pix-payload', 'expirationDate' => '2026-12-31 23:59:59', 'description' => 'Mensalidade',
            ]),
            str_contains($request->url(), '/payments') => Http::response(['id' => 'pay_1', 'invoiceUrl' => 'https://asaas.test/i/pay_1']),
            $request->method() === 'GET' && str_contains($request->url(), '/customers') => Http::response(['data' => []]),
            str_contains($request->url(), '/customers') => Http::response(['id' => 'cus_1']),
            default => Http::response([], 404),
        });
    }
}
