<?php

namespace Tests\Feature;

use App\Documents\AttendanceDeclaration;
use App\Documents\FinancialClearanceDeclaration;
use App\Enums\IssuedDocumentType;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Enrollments\Pages\ViewEnrollment;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\IssuedDocument;
use App\Models\Level;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceAndClearanceDeclarationsTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Enrollment $enrollment;

    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(10, 0));
        Storage::fake('local');
        $this->seed(RolePermissionSeeder::class);

        $company = Company::create([
            'name' => 'Escola Ceci', 'email' => 'escola@example.test',
            'document_signer_name' => 'Joana Albuquerque', 'document_signer_role' => 'Diretora',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole('secretary');
        $this->actingAs($user);

        $this->customer = Customer::create(['name' => 'Maria Responsável', 'document' => '096.234.214-94']);
        $student = Student::create([
            'name' => 'Ana Clara', 'customer_id' => $this->customer->id, 'date_of_birth' => '2015-03-10', 'gender' => 'F',
            'affiliation_1' => 'Maria Responsável',
        ]);
        Student::create(['name' => 'Pedro', 'customer_id' => $this->customer->id, 'date_of_birth' => '2017-05-01', 'gender' => 'M']);
        $classroom = Classroom::create(['name' => 'Turma A', 'level_id' => Level::create(['name' => 'Fundamental'])->id]);
        $year = AcademicYear::create(['description' => '2026', 'year' => 2026, 'start_at' => '2026-02-02', 'end_at' => '2026-12-18', 'is_default' => true]);
        $this->enrollment = Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id,
            'enrollment_date' => '2026-01-15', 'status' => 'active',
        ]);
        $this->teacher = Teacher::create(['name' => 'Professora', 'email' => 'professora@example.test']);
    }

    private function attend(string $date, bool $present = true, bool $justified = false, ?Enrollment $enrollment = null): Attendance
    {
        $enrollment ??= $this->enrollment;

        return Attendance::create([
            'enrollment_id' => $enrollment->id, 'classroom_id' => $enrollment->classroom_id, 'teacher_id' => $this->teacher->id,
            'date' => $date, 'present' => $present, 'justified' => $justified,
        ]);
    }

    /** 9 aulas no período: 7 presenças, 2 faltas (1 justificada). */
    private function regularAttendance(): void
    {
        foreach (['2026-03-02', '2026-03-03', '2026-04-01', '2026-05-04', '2026-06-01', '2026-08-03', '2026-10-22'] as $date) {
            $this->attend($date);
        }
        $this->attend('2026-09-01', present: false, justified: true);
        $this->attend('2026-09-02', present: false);
    }

    private function invoice(string $dueDate, string $status, float $balance, string $reference = 'Mensalidade'): void
    {
        DB::table('invoices')->insert([
            'customer_id' => $this->customer->id, 'reference' => $reference, 'issue_date' => $dueDate, 'due_date' => $dueDate,
            'amount' => 500, 'balance' => $balance, 'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_attendance_is_counted_from_the_school_year_start_until_today(): void
    {
        $this->regularAttendance();
        $this->attend('2026-01-20', present: false);             // antes do início do ano letivo
        $this->attend('2026-10-30');                             // depois de hoje
        $this->attend('2026-10-21', present: false)->delete();   // chamada excluída

        $summary = AttendanceDeclaration::summary($this->enrollment);

        $this->assertSame('2026-02-02', $summary['from']->toDateString());
        $this->assertSame([9, 7, 2, 1, 77.8], [$summary['total'], $summary['present'], $summary['absent'], $summary['justified'], $summary['percentage']]);
        $this->assertSame('Frequência de 77,8% até hoje: 7 presenças em 9 aulas registradas desde 02/02/2026.', AttendanceDeclaration::describe($summary));
    }

    public function test_attendance_declaration_is_issued_from_the_enrollment_with_the_summary(): void
    {
        $this->regularAttendance();

        Livewire::test(ViewEnrollment::class, ['record' => $this->enrollment->getKey()])
            ->callAction('issueAttendanceDeclaration')
            ->assertNotified('Documento emitido');

        $document = IssuedDocument::sole();
        $this->assertSame(IssuedDocumentType::AttendanceDeclaration, $document->type);
        $this->assertSame('DEC-2026-000001', $document->number);
        $this->assertTrue($document->hasIntactContent());

        $this->get(route('issued-documents.print', $document))
            ->assertSee('Declaração de frequência')
            ->assertSee('que <strong>Ana Clara</strong>, filha de <strong>Maria Responsável</strong>, nascida em 10/03/2015,', false)
            ->assertSee('No período de 02/02/2026 a 23/10/2026, a aluna obteve frequência de <strong>77,8%</strong>, com 7 presenças em 9 aulas registradas e 2 faltas, das quais 1 justificada.', false);
    }

    public function test_attendance_text_covers_all_justified_and_no_absences(): void
    {
        $this->attend('2026-03-02');
        $this->attend('2026-03-03', present: false, justified: true);
        $this->attend('2026-03-04', present: false, justified: true);
        $this->get(route('issued-documents.print', AttendanceDeclaration::issue($this->enrollment, auth()->user())))
            ->assertSee('com 1 presença em 3 aulas registradas e 2 faltas, todas justificadas.');

        Attendance::query()->where('present', false)->update(['present' => true]);
        $this->get(route('issued-documents.print', AttendanceDeclaration::issue($this->enrollment, auth()->user())))
            ->assertSee('frequência de <strong>100%</strong>, com 3 presenças em 3 aulas registradas.', false);
    }

    public function test_attendance_declaration_requires_registered_attendance(): void
    {
        $this->attend('2026-03-02', enrollment: Enrollment::create([
            'student_id' => $this->enrollment->student_id, 'classroom_id' => $this->enrollment->classroom_id,
            'academic_year_id' => AcademicYear::create(['description' => '2025', 'year' => 2025, 'is_default' => false])->id,
            'enrollment_date' => '2025-02-01', 'status' => 'completed',
        ]));

        Livewire::test(ViewEnrollment::class, ['record' => $this->enrollment->getKey()])
            ->callAction('issueAttendanceDeclaration')
            ->assertNotified('Documento não emitido');

        $this->assertSame(AttendanceDeclaration::NO_ATTENDANCE_MESSAGE, AttendanceDeclaration::describe(AttendanceDeclaration::summary($this->enrollment)));

        $this->assertSame(0, IssuedDocument::count());
    }

    public function test_clearance_is_refused_while_there_are_overdue_invoices(): void
    {
        $this->invoice('2026-09-10', 'overdue', 500, 'Mensalidade 09/2026');
        $this->invoice('2026-10-10', 'partial', 200, 'Mensalidade 10/2026');

        Livewire::test(EditCustomer::class, ['record' => $this->customer->getKey()])
            ->callAction('issueFinancialClearance')
            ->assertNotified('Documento não emitido');

        $this->assertSame(
            "Há 2 faturas com saldo a pagar: Mensalidade 09/2026 (venc. 10/09/2026, saldo R\$\u{a0}500,00); Mensalidade 10/2026 (venc. 10/10/2026, saldo R\$\u{a0}200,00). Registre os pagamentos antes de emitir a declaração.",
            FinancialClearanceDeclaration::describePending(FinancialClearanceDeclaration::pendingInvoices($this->customer)),
        );

        $this->assertSame(0, IssuedDocument::count());
    }

    public function test_clearance_until_today_ignores_paid_canceled_settled_and_future_invoices(): void
    {
        $this->invoice('2026-08-10', 'paid', 0);
        $this->invoice('2026-08-10', 'canceled', 500);
        $this->invoice('2026-09-10', 'open', 0);
        $this->invoice('2026-10-23', 'open', 500);   // vence hoje: ainda não está vencida
        $this->invoice('2026-11-10', 'open', 500);

        Livewire::test(EditCustomer::class, ['record' => $this->customer->getKey()])
            ->callAction('issueFinancialClearance', ['valid_for_days' => 30])
            ->assertNotified('Documento emitido');

        $document = IssuedDocument::sole();
        $this->assertSame(IssuedDocumentType::FinancialClearanceDeclaration, $document->type);
        $this->assertTrue($document->documentable->is($this->customer));
        $this->assertSame('2026-11-22', $document->valid_until->toDateString());

        $this->get(route('issued-documents.print', $document))
            ->assertSee('Declaração de quitação')
            ->assertSee('que <strong>Maria Responsável</strong>, CPF 096.234.214-94,', false)
            ->assertSee('responsável pelo pagamento das mensalidades de <strong>Ana Clara</strong> e de <strong>Pedro</strong>', false)
            ->assertSee('não possui débitos vencidos com esta instituição de ensino até a presente data, <strong>23/10/2026</strong>.', false)
            ->assertSee('não abrange mensalidades e faturas com vencimento posterior');

        auth()->logout();
        $this->get(route('documents.verify', $document->formattedCode()))
            ->assertSee('CPF ***.234.214-**')
            ->assertDontSee('096.234.214-94');
    }

    public function test_annual_clearance_requires_every_invoice_of_a_closed_year_to_be_paid(): void
    {
        $this->invoice('2025-12-10', 'open', 500, 'Mensalidade 12/2025');
        $this->invoice('2026-09-10', 'overdue', 500);   // de outro ano: não impede a quitação de 2025

        try {
            FinancialClearanceDeclaration::issue($this->customer, auth()->user(), 2025);
            $this->fail('A fatura de 2025 em aberto deveria impedir a quitação anual.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Mensalidade 12/2025', collect($e->errors())->flatten()->first());
        }

        DB::table('invoices')->where('reference', 'Mensalidade 12/2025')->update(['status' => 'paid', 'balance' => 0]);
        $document = FinancialClearanceDeclaration::issue($this->customer, auth()->user(), 2025);

        $this->get(route('issued-documents.print', $document))
            ->assertSee('quitou todos os débitos com esta instituição de ensino referentes ao ano de <strong>2025</strong>.', false)
            ->assertSee('nos termos da Lei nº 12.007/2009');

        $this->expectException(ValidationException::class);
        FinancialClearanceDeclaration::issue($this->customer, auth()->user(), 2026);
    }

    public function test_annual_clearance_is_chosen_in_the_customer_action(): void
    {
        $this->assertSame([2025 => '2025', 2024 => '2024', 2023 => '2023', 2022 => '2022', 2021 => '2021'], FinancialClearanceDeclaration::closedYears());

        Livewire::test(EditCustomer::class, ['record' => $this->customer->getKey()])
            ->callAction('issueFinancialClearance', ['year' => 2025, 'valid_for_days' => null])
            ->assertNotified('Documento emitido');

        $document = IssuedDocument::sole();
        $this->assertSame(2025, $document->data['content']['period']['year']);
        $this->assertNull($document->valid_until);
    }
}
