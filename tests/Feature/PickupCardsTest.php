<?php

namespace Tests\Feature;

use App\Documents\PickupCard;
use App\Enums\IssuedDocumentStatus;
use App\Enums\IssuedDocumentType;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Filament\Resources\Students\RelationManagers\PickupAuthorizationsRelationManager;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\IssuedDocument;
use App\Models\Level;
use App\Models\Student;
use App\Models\StudentPickupAuthorization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PickupCardsTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    private StudentPickupAuthorization $grandma;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(10, 0));
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);

        $company = Company::create([
            'name' => 'Escola Ceci', 'email' => 'escola@example.test', 'phone' => '(83) 3333-4444',
            'document_signer_name' => 'Joana Albuquerque', 'document_signer_role' => 'Diretora',
        ]);
        $user = User::factory()->create(['name' => 'Secretária', 'company_id' => $company->id]);
        $user->assignRole('secretary');
        $this->actingAs($user);

        Storage::disk('local')->put('student-photos/ana.jpg', UploadedFile::fake()->image('ana.jpg', 30, 40)->getContent());
        $customer = Customer::create(['name' => 'Maria Responsável', 'document' => '096.234.214-94']);
        $this->student = Student::create([
            'name' => 'Ana Clara de Souza', 'photo_path' => 'student-photos/ana.jpg', 'customer_id' => $customer->id,
            'date_of_birth' => '2015-03-10', 'gender' => 'F',
        ]);
        $classroom = Classroom::create(['name' => 'Turma A', 'level_id' => Level::create(['name' => 'Fundamental'])->id]);
        $year = AcademicYear::create(['description' => '2026', 'year' => 2026, 'start_at' => '2026-02-02', 'end_at' => '2026-12-18', 'is_default' => true]);
        Enrollment::create([
            'student_id' => $this->student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id,
            'enrollment_date' => '2026-02-01', 'status' => 'active',
        ]);

        $this->grandma = $this->student->pickupAuthorizations()->create([
            'name' => 'Rita de Souza', 'document' => '123.456.789-09', 'relationship' => 'Avó', 'phone' => '(83) 98888-7777',
        ]);
    }

    private function relationManager()
    {
        return Livewire::test(PickupAuthorizationsRelationManager::class, ['ownerRecord' => $this->student, 'pageClass' => EditStudent::class]);
    }

    public function test_card_is_issued_from_the_student_with_photo_and_school_year_validity(): void
    {
        $this->relationManager()
            ->callTableAction('issuePickupCard', $this->grandma)
            ->assertNotified('Documento emitido');

        $card = IssuedDocument::sole();
        $this->assertSame(IssuedDocumentType::PickupCard, $card->type);
        $this->assertSame('CAR-2026-000001', $card->number);
        $this->assertSame('2026-12-18', $card->valid_until->toDateString());
        $this->assertTrue($card->documentable->is($this->grandma));
        $this->assertSame(['name' => 'Rita de Souza', 'document' => '123.456.789-09', 'relationship' => 'Avó'], $card->data['content']['person']);
        $this->assertSame('Fundamental – Turma A', $card->data['content']['student']['grade']);
        Storage::disk('local')->assertExists($card->data['content']['student']['photo']);
        $this->assertTrue($card->hasIntactContent());

        $this->get(route('issued-documents.print', $card))
            ->assertOk()
            ->assertSee(['Autorização', 'Ana Clara de Souza', 'Fundamental – Turma A', 'Ano letivo 2026', 'Rita de Souza', 'Avó · Doc. 123.456.789-09'])
            ->assertSee(['CAR-2026-000001', 'Válida até', '18/12/2026', $card->formattedCode(), '(83) 3333-4444'])
            ->assertSee('class="photo" src="data:image/jpeg;base64,', false)
            ->assertSee('src="data:image/svg+xml;base64,', false)
            ->assertSee('Recorte na linha pontilhada');
    }

    public function test_asking_again_reopens_the_same_card(): void
    {
        $first = PickupCard::issue($this->grandma, auth()->user());

        $this->relationManager()
            ->callTableAction('issuePickupCard', $this->grandma)
            ->assertNotified('Documento já emitido');

        $this->assertTrue(PickupCard::issue($this->grandma, auth()->user())->is($first));
        $this->assertSame(1, IssuedDocument::count());
    }

    public function test_revoking_cancels_the_card_and_blocks_new_ones(): void
    {
        $card = PickupCard::issue($this->grandma, auth()->user());

        $this->relationManager()
            ->callTableAction('revoke', $this->grandma, ['reason' => 'Pedido da mãe'])
            ->assertNotified('Autorização revogada');

        $this->assertTrue($this->grandma->fresh()->isRevoked());
        $card->refresh();
        $this->assertSame(IssuedDocumentStatus::Canceled, $card->status());
        $this->assertSame('Autorização revogada em 23/10/2026: Pedido da mãe', $card->cancel_reason);

        $this->relationManager()
            ->assertTableActionHidden('issuePickupCard', $this->grandma)
            ->assertTableActionHidden('revoke', $this->grandma);

        $this->expectException(ValidationException::class);
        PickupCard::issue($this->grandma->fresh(), auth()->user());
    }

    public function test_changing_printed_data_cancels_the_card_but_phone_does_not(): void
    {
        $card = PickupCard::issue($this->grandma, auth()->user());

        $this->grandma->update(['phone' => '(83) 97777-6666']);
        $this->assertNull($card->fresh()->canceled_at);

        $this->grandma->update(['document' => '987.654.321-00']);
        $this->assertSame('Dados do autorizado alterados em 23/10/2026; emita uma nova carteirinha.', $card->fresh()->cancel_reason);

        $this->assertSame('CAR-2026-000002', PickupCard::issue($this->grandma->fresh(), auth()->user())->number);
    }

    public function test_deleting_the_authorization_or_the_student_cancels_cards(): void
    {
        $card = PickupCard::issue($this->grandma, auth()->user());
        $uncle = $this->student->pickupAuthorizations()->create(['name' => 'Carlos', 'document' => '1234567', 'relationship' => 'Tio']);
        $uncleCard = PickupCard::issue($uncle, auth()->user());

        $uncle->delete();
        $this->assertSame('Autorização excluída em 23/10/2026.', $uncleCard->fresh()->cancel_reason);

        $this->student->delete();
        $this->assertSame('Cadastro do aluno excluído em 23/10/2026.', $card->fresh()->cancel_reason);
        Storage::disk('local')->assertMissing('student-photos/ana.jpg');
        Storage::disk('local')->assertExists($card->data['content']['student']['photo']);
    }

    public function test_card_requires_an_active_enrollment(): void
    {
        $this->student->enrollments()->update(['status' => 'canceled']);

        $this->relationManager()
            ->callTableAction('issuePickupCard', $this->grandma)
            ->assertNotified('Documento não emitido');

        $this->assertSame(0, IssuedDocument::count());
    }

    public function test_gate_check_shows_only_what_the_door_needs_to_guests(): void
    {
        $card = PickupCard::issue($this->grandma, auth()->user());
        auth()->logout();

        $this->get(route('documents.verify', $card->formattedCode()))
            ->assertOk()
            ->assertSee(['Carteirinha válida', 'Rita de Souza', 'Avó', 'doc. ***.456.789-**', 'Ana C. S.', 'Fundamental – Turma A'])
            ->assertDontSee(['Ana Clara de Souza', '123.456.789-09'])
            ->assertDontSee('data:image/jpeg', false);

        $this->actingAs(User::role('secretary')->first());
        $this->get(route('documents.verify', $card->formattedCode()))
            ->assertSee('Ana Clara de Souza')
            ->assertSee('data:image/jpeg', false);

        PickupCard::revoke($this->grandma);
        auth()->logout();
        $this->get(route('documents.verify', $card->formattedCode()))
            ->assertSee(['Documento cancelado', 'Autorização revogada em 23/10/2026.'])
            ->assertDontSee('Rita de Souza');
    }

    public function test_cards_are_issued_and_printed_together_four_per_sheet(): void
    {
        foreach (['Carlos', 'Paula', 'Bruno', 'Lia'] as $name) {
            $this->student->pickupAuthorizations()->create(['name' => $name, 'document' => '1234567', 'relationship' => 'Outro']);
        }
        $this->student->pickupAuthorizations()->create(['name' => 'Revogado', 'document' => '1', 'relationship' => 'Tio', 'revoked_at' => now()]);

        $this->relationManager()
            ->callTableAction('printAllCards')
            ->assertNotified('5 carteirinhas prontas');

        $ids = IssuedDocument::pluck('id')->all();
        $this->assertCount(5, $ids);

        $html = $this->get(route('pickup-cards.print', ['ids' => $ids]))->assertOk()->assertDontSee('Revogado')->getContent();
        $this->assertSame(2, substr_count($html, '<main class="sheet">'));
        $this->assertSame(5, substr_count($html, 'class="card-pair"'));
    }

    public function test_only_the_office_manages_authorizations_and_prints_cards(): void
    {
        $card = PickupCard::issue($this->grandma, auth()->user());

        $this->relationManager()
            ->callTableAction('create', data: ['name' => 'Paulo Motorista', 'relationship' => 'Motorista do transporte escolar', 'document' => '7654321'])
            ->assertHasNoTableActionErrors();
        $this->assertSame(2, $this->student->pickupAuthorizations()->count());
        $this->relationManager()->assertTableActionHidden('delete', $this->grandma);

        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');
        $this->actingAs($teacher);

        $this->assertFalse(PickupAuthorizationsRelationManager::canViewForRecord($this->student, EditStudent::class));
        $this->get(route('issued-documents.print', $card))->assertForbidden();
        $this->get(route('pickup-cards.print', ['ids' => [$card->id]]))->assertForbidden();
    }

    public function test_student_names_are_abbreviated_for_the_gate(): void
    {
        $this->assertSame('Ana C. S.', PickupCard::abbreviate('Ana Clara de Souza'));
        $this->assertSame('Pedro', PickupCard::abbreviate('Pedro'));
        $this->assertSame('João P. A.', PickupCard::abbreviate('João  Pedro dos Anjos'));
    }
}
