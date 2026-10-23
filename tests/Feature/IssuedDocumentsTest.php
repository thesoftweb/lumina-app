<?php

namespace Tests\Feature;

use App\Documents\EnrollmentDeclaration;
use App\Enums\IssuedDocumentStatus;
use App\Filament\Resources\Enrollments\Pages\ViewEnrollment;
use App\Filament\Resources\IssuedDocuments\IssuedDocumentResource;
use App\Filament\Resources\IssuedDocuments\Pages\ListIssuedDocuments;
use App\Models\AcademicYear;
use App\Models\City;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\IssuedDocument;
use App\Models\Level;
use App\Models\State;
use App\Models\Student;
use App\Models\User;
use App\Services\DocumentIssuer;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class IssuedDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(10, 0));
        Storage::fake('public');
        Storage::fake('local');
        $this->seed(RolePermissionSeeder::class);

        State::forceCreate(['code' => 25, 'name' => 'Paraíba', 'abbreviation' => 'PB']);
        $city = City::forceCreate(['code' => 2513703, 'name' => 'Santa Rita', 'state_id' => 25]);
        Storage::disk('public')->put('company-logos/logo.png', UploadedFile::fake()->image('logo.png', 20, 20)->getContent());

        $this->company = Company::create([
            'name' => 'Escola Ceci', 'email' => 'escola@example.test', 'tax_id' => '11.222.333/0001-81', 'inep_code' => '25012345',
            'address' => 'Av. Principal', 'address_number' => '100', 'neighborhood' => 'Centro', 'city_id' => $city->id,
            'logo_path' => 'company-logos/logo.png', 'document_signer_name' => 'Joana Albuquerque', 'document_signer_role' => 'Diretora',
        ]);

        $customer = Customer::create(['name' => 'Maria Responsável', 'document' => '096.234.214-94']);
        $student = Student::create([
            'name' => 'Ana Clara', 'customer_id' => $customer->id, 'date_of_birth' => '2015-03-10', 'gender' => 'F', 'doc_number' => '123.456.789-09',
            'affiliation_1' => 'Maria Responsável', 'affiliation_2' => 'João Pereira',
        ]);
        $classroom = Classroom::create(['name' => 'Turma A', 'level_id' => Level::create(['name' => 'Fundamental'])->id]);
        $year = AcademicYear::create(['description' => '2026', 'year' => 2026, 'is_default' => true]);

        $this->enrollment = Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id,
            'enrollment_date' => '2026-02-01', 'status' => 'active',
        ]);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create(['name' => ucfirst($role), 'company_id' => $this->company->id]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function issue(?string $purpose = null, ?int $validForDays = null): IssuedDocument
    {
        return EnrollmentDeclaration::issue($this->enrollment, auth()->user() ?? $this->actingAsRole('secretary'), $purpose, $validForDays);
    }

    public function test_secretary_issues_the_declaration_from_the_enrollment(): void
    {
        $secretary = $this->actingAsRole('secretary');

        Livewire::test(ViewEnrollment::class, ['record' => $this->enrollment->getKey()])
            ->callAction('issueEnrollmentDeclaration', ['purpose' => 'transporte escolar', 'valid_for_days' => 30])
            ->assertNotified('Declaração emitida');

        $document = IssuedDocument::sole();
        $this->assertSame('DEC-2026-000001', $document->number);
        $this->assertMatchesRegularExpression('/^['.IssuedDocument::CODE_ALPHABET.']{16}$/', $document->verification_code);
        $this->assertSame('Ana Clara', $document->subject);
        $this->assertTrue($document->documentable->is($this->enrollment));
        $this->assertSame('transporte escolar', $document->purpose);
        $this->assertSame('2026-11-22', $document->valid_until->toDateString());
        $this->assertTrue($document->issuer->is($secretary));
        $this->assertSame(IssuedDocumentStatus::Valid, $document->status());
        $this->assertTrue($document->hasIntactContent());
        $this->assertSame('Fundamental', $document->data['content']['enrollment']['level']);
        Storage::disk('local')->assertExists($document->data['company']['logo']);
    }

    public function test_numbers_follow_the_series_and_restart_each_year(): void
    {
        $this->assertSame('DEC-2026-000001', $this->issue()->number);
        $this->assertSame('DEC-2026-000002', $this->issue()->number);

        $this->travelTo(now()->setDate(2027, 1, 4));
        $this->assertSame('DEC-2027-000001', $this->issue()->number);
    }

    public function test_content_stays_as_issued_when_records_change(): void
    {
        $document = $this->issue();

        $this->enrollment->student->update(['name' => 'Outro Nome']);
        Storage::disk('public')->put('company-logos/nova.png', UploadedFile::fake()->image('nova.png', 30, 30)->getContent());
        $this->company->update(['name' => 'Escola Renomeada', 'document_signer_name' => 'Outra Pessoa', 'logo_path' => 'company-logos/nova.png']);

        $this->get(route('issued-documents.print', $document))
            ->assertOk()
            ->assertSee(['Ana Clara', 'Escola Ceci', 'Joana Albuquerque'])
            ->assertDontSee(['Outro Nome', 'Escola Renomeada', 'Outra Pessoa']);

        $this->assertTrue($document->fresh()->hasIntactContent());
        $this->assertNotNull($document->fresh()->logoDataUri(), 'a cópia da logo antiga continua disponível');
    }

    public function test_issued_document_cannot_be_changed_or_deleted(): void
    {
        $document = $this->issue();

        try {
            $document->update(['subject' => 'Outro']);
            $this->fail('Alteração deveria ser recusada.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $document->delete();
    }

    public function test_declaration_requires_an_active_enrollment_and_a_configured_signer(): void
    {
        $this->actingAsRole('secretary');

        $this->enrollment->update(['status' => 'canceled']);
        Livewire::test(ViewEnrollment::class, ['record' => $this->enrollment->getKey()])
            ->assertActionHidden('issueEnrollmentDeclaration');

        $this->enrollment->update(['status' => 'active']);
        $this->company->update(['document_signer_name' => null, 'document_signer_role' => null]);
        Livewire::test(ViewEnrollment::class, ['record' => $this->enrollment->getKey()])
            ->callAction('issueEnrollmentDeclaration', ['valid_for_days' => 90])
            ->assertNotified('Declaração não emitida');

        $this->assertSame(0, IssuedDocument::count());
    }

    public function test_print_page_is_ready_to_sign_and_requires_the_office(): void
    {
        $document = $this->issue('transporte escolar', 90);
        $url = route('issued-documents.print', ['issuedDocument' => $document, 'print' => 1]);
        auth()->logout();

        $this->get($url)->assertRedirect(route('filament.admin.auth.login'));

        $this->actingAsRole('teacher');
        $this->get($url)->assertForbidden();

        $this->actingAsRole('secretary');
        $this->get($url)
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee(['Declaração de matrícula', 'Nº DEC-2026-000001'])
            ->assertSee('fonts/filament/filament/inter/index.css', false)
            ->assertSee('Declaramos, para os devidos fins, que <strong>Ana Clara</strong>, filha de <strong>Maria Responsável</strong> e de <strong>João Pereira</strong>, nascida em 10/03/2015, CPF 123.456.789-09', false)
            ->assertSee('regularmente matriculada')
            ->assertSee('em <strong>Fundamental – Turma A</strong>', false)
            ->assertSee('para fins de transporte escolar')
            ->assertSee('Santa Rita/PB, 23 de outubro de 2026.')
            ->assertSee(['Joana Albuquerque', 'Diretora', 'CNPJ 11.222.333/0001-81  ·  INEP 25012345'])
            ->assertSee(['Verificação de autenticidade', 'válido até', '21/01/2027', $document->formattedCode(), route('documents.verify.form')])
            ->assertSee('src="data:image/svg+xml;base64,', false)
            ->assertSee('src="data:image/png;base64,', false)
            ->assertSee('window.print()', false);
    }

    public function test_declaration_text_follows_gender_and_known_parents(): void
    {
        $this->enrollment->student->update(['gender' => 'M', 'name' => 'Pedro', 'affiliation_1' => null, 'affiliation_2' => 'João Pereira']);
        $document = $this->issue();

        $this->get(route('issued-documents.print', $document))
            ->assertSee('que <strong>Pedro</strong>, filho de <strong>João Pereira</strong>, nascido em 10/03/2015', false)
            ->assertSee('regularmente matriculado nesta');

        $this->enrollment->student->update(['affiliation_2' => null]);
        $this->get(route('issued-documents.print', $this->issue()))
            ->assertSee('que <strong>Pedro</strong>, nascido em', false)
            ->assertDontSee('filho de');
    }

    public function test_verification_shows_the_original_with_the_cpf_masked(): void
    {
        $document = $this->issue();
        auth()->logout();

        $this->get(route('documents.verify', $document->formattedCode()))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Documento válido')
            ->assertSee(['Ana Clara', 'CPF ***.456.789-**', 'Escola Ceci'])
            ->assertDontSee('123.456.789-09');

        // Digitado em minúsculas, sem hífens e com O no lugar de 0
        $typed = strtolower(str_replace('0', 'O', $document->verification_code));
        $this->get(route('documents.verify', $typed))->assertSee('Documento válido');

        $this->get(route('documents.verify.form', ['codigo' => $typed]))
            ->assertRedirect(route('documents.verify', $typed));
    }

    public function test_verification_reports_unknown_expired_canceled_and_altered_documents(): void
    {
        $this->get(route('documents.verify', 'AAAA-BBBB-CCCC-DDDD'))->assertSee('Documento não encontrado');
        $this->get(route('documents.verify', 'curto'))->assertSee('Documento não encontrado');

        $expiring = $this->issue(validForDays: 30);
        $canceled = $this->issue();
        $altered = $this->issue();
        $admin = $this->actingAsRole('admin');
        app(DocumentIssuer::class)->cancel($canceled, $admin, 'Emitido com dados errados');
        $data = $altered->data;
        $data['content']['student']['name'] = 'Nome Trocado';
        DB::table('issued_documents')->where('id', $altered->id)->update(['data' => json_encode($data)]);

        $this->get(route('documents.verify', $canceled->formattedCode()))
            ->assertSee(['Documento cancelado', 'Emitido com dados errados', 'CANCELADO']);

        $this->get(route('documents.verify', $altered->formattedCode()))
            ->assertSee('Não foi possível confirmar este documento')
            ->assertDontSee('Nome Trocado');

        $this->get(route('documents.verify', $expiring->formattedCode()))->assertSee('Documento válido');
        $this->travelTo(now()->addDays(31));
        $this->get(route('documents.verify', $expiring->formattedCode()))->assertSee('Documento autêntico, mas vencido');
    }

    public function test_verification_is_rate_limited(): void
    {
        foreach (range(1, 30) as $attempt) {
            $this->get(route('documents.verify', 'AAAA-BBBB-CCCC-DDDD'))->assertOk();
        }

        $this->get(route('documents.verify', 'AAAA-BBBB-CCCC-DDDD'))->assertTooManyRequests();
    }

    public function test_office_lists_and_finds_documents_by_code(): void
    {
        $first = $this->issue();
        $second = $this->issue();

        $this->actingAsRole('secretary');
        $this->get(IssuedDocumentResource::getUrl('index'))->assertOk();

        Livewire::test(ListIssuedDocuments::class)
            ->assertCanSeeTableRecords([$first, $second])
            ->searchTable(strtolower($second->verification_code))
            ->assertCanSeeTableRecords([$second])
            ->assertCanNotSeeTableRecords([$first]);

        $this->actingAsRole('teacher');
        $this->get(IssuedDocumentResource::getUrl('index'))->assertForbidden();
    }

    public function test_only_admin_cancels_and_a_reason_is_required(): void
    {
        $document = $this->issue();

        $this->actingAsRole('secretary');
        Livewire::test(ListIssuedDocuments::class)->assertTableActionHidden('cancel', $document);

        $this->actingAsRole('admin');
        // Como na tela: o modal continua aberto após o erro, o motivo é corrigido e enviado de novo.
        $page = Livewire::test(ListIssuedDocuments::class)
            ->mountTableAction('cancel', $document)
            ->setTableActionData(['reason' => 'curto'])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['reason' => 'min']);
        $this->assertNull($document->fresh()->canceled_at);

        $page->setTableActionData(['reason' => 'Nome do aluno digitado errado'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors()
            ->assertNotified('Documento cancelado');

        $document->refresh();
        $this->assertSame(IssuedDocumentStatus::Canceled, $document->status());
        $this->assertSame('Nome do aluno digitado errado', $document->cancel_reason);
        $this->assertTrue($document->hasIntactContent());

        Livewire::test(ListIssuedDocuments::class)
            ->assertTableActionHidden('cancel', $document)
            ->filterTable('status', IssuedDocumentStatus::Canceled->value)
            ->assertCanSeeTableRecords([$document]);
    }
}
