<?php

namespace Tests\Feature;

use App\Documents\SchoolTranscript;
use App\Enums\IssuedDocumentType;
use App\Enums\TranscriptResult;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Filament\Resources\Students\RelationManagers\TranscriptYearsRelationManager;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomSubject;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\IssuedDocument;
use App\Models\Level;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TranscriptYear;
use App\Models\User;
use App\Services\Academic\YearClosing;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Histórico escolar: anos de outras escolas, anos desta escola, emissão, PDF e verificação. */
class SchoolTranscriptTest extends TestCase
{
    use RefreshDatabase;

    private User $secretary;

    private Student $student;

    private Classroom $classroom;

    private ClassroomSubject $math;

    private Enrollment $enrollment2025;

    private Enrollment $enrollment2026;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(10, 0));
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);

        $company = Company::create([
            'name' => 'Escola Ceci', 'email' => 'escola@example.test', 'tax_id' => '12.345.678/0001-95', 'inep_code' => '25012345',
            'document_signer_name' => 'Joana Albuquerque', 'document_signer_role' => 'Diretora',
            'school_secretary_name' => 'João Pereira', 'school_secretary_registration' => 'Portaria nº 12/2020',
        ]);
        $this->secretary = User::factory()->create(['name' => 'Secretária', 'company_id' => $company->id]);
        $this->secretary->assignRole('secretary');
        $this->actingAs($this->secretary);

        $customer = Customer::create(['name' => 'Maria de Souza', 'document' => '096.234.214-94']);
        $this->student = Student::create([
            'name' => 'Ana Clara de Souza', 'customer_id' => $customer->id, 'date_of_birth' => '2017-03-10', 'gender' => 'F',
            'city_of_birth' => 'Santa Rita', 'affiliation_1' => 'Maria de Souza', 'affiliation_2' => 'Carlos de Souza', 'doc_number' => '123.456.789-09',
        ]);

        $this->classroom = Classroom::create(['name' => '3º A', 'grade_label' => '3º ano', 'school_days' => 200, 'level_id' => Level::create(['name' => 'Fundamental'])->id]);
        $this->classroom->subjects()->attach(Subject::create(['name' => 'Matemática'])->id);
        $this->math = $this->classroom->classroomSubjects()->sole();
        $this->math->update(['workload_hours' => 160]);

        $this->enrollment2025 = $this->enroll(2025, 'completed');
        $this->enrollment2026 = $this->enroll(2026, 'active');
    }

    private function enroll(int $year, string $status): Enrollment
    {
        $academicYear = AcademicYear::create(['description' => (string) $year, 'year' => $year, 'start_at' => "{$year}-02-02", 'end_at' => "{$year}-12-18"]);
        $enrollment = Enrollment::create([
            'student_id' => $this->student->id, 'customer_id' => $this->student->customer_id, 'classroom_id' => $this->classroom->id,
            'academic_year_id' => $academicYear->id, 'enrollment_date' => "{$year}-02-01", 'status' => $status,
        ]);

        foreach ([1, 2] as $order) {
            $term = Term::firstOrCreate(['year' => $year, 'order' => $order], ['name' => "{$order}º Bimestre"]);

            if ($year === 2025 || $order === 1) {
                Grade::create(['enrollment_id' => $enrollment->id, 'classroom_subject_id' => $this->math->id, 'term_id' => $term->id, 'grade' => $order === 1 ? 7 : 9]);
            }
        }

        return $enrollment;
    }

    private function tab()
    {
        return Livewire::test(TranscriptYearsRelationManager::class, ['ownerRecord' => $this->student, 'pageClass' => EditStudent::class]);
    }

    public function test_year_from_another_school_is_entered_from_the_transfer_documents(): void
    {
        $this->tab()
            ->callTableAction('create', data: [
                'year' => 2024, 'grade_label' => '2º ano', 'assessment_mode' => 'numeric', 'school_name' => 'Escola Municipal Aurora',
                'school_city' => 'João Pessoa', 'school_state' => 'pb', 'result' => 'approved', 'attendance_percent' => 96, 'school_days' => 200,
                'subjects' => [
                    ['name' => 'Matemática', 'final_grade' => 8.5, 'workload_hours' => 160],
                    ['name' => 'Língua Portuguesa', 'final_grade' => 7, 'workload_hours' => 200],
                ],
            ])
            ->assertHasNoTableActionErrors();

        $year = TranscriptYear::sole();
        $this->assertSame(TranscriptYear::EXTERNAL, $year->source);
        $this->assertSame('João Pessoa/PB', $year->schoolPlace());
        $this->assertSame(360, $year->total_workload);
        $this->assertSame(['Matemática' => '8,50', 'Língua Portuguesa' => '7,00'], $year->subjects->mapWithKeys(fn ($subject) => [$subject->name => $subject->resultLabel()])->all());
    }

    public function test_year_of_this_school_is_recorded_from_the_enrollment(): void
    {
        $this->tab()
            ->callTableAction('recordEnrollment', data: ['enrollment_id' => $this->enrollment2025->id])
            ->assertNotified('Ano registrado no histórico');

        $year = TranscriptYear::sole();
        $this->assertTrue($year->isInternal());
        $this->assertSame(TranscriptResult::Approved, $year->result);
        $this->assertSame('8,00', $year->subjects->sole()->resultLabel());
    }

    public function test_class_council_adjustment_requires_a_reason(): void
    {
        $year = app(YearClosing::class)->closeEnrollment($this->enrollment2025, $this->secretary);

        $this->tab()
            ->callTableAction('adjust', $year, ['result' => 'failed', 'reason' => ''])
            ->assertNotified('Informe o motivo do resultado diferente do calculado');
        $this->assertSame(TranscriptResult::Approved, $year->fresh()->result);

        $this->tab()
            ->callTableAction('adjust', $year, ['result' => 'failed', 'reason' => 'Conselho de classe'])
            ->assertNotified('Resultado ajustado');
        $this->assertTrue($year->fresh()->isAdjusted());
        $this->assertTableActionHiddenFor($year);
    }

    private function assertTableActionHiddenFor(TranscriptYear $year): void
    {
        // Ano desta escola não é editado como ano de outra escola: as notas vêm da matrícula
        $this->tab()->assertTableActionHidden('edit', $year)->assertTableActionVisible('adjust', $year);
    }

    public function test_transcript_is_issued_with_previous_years_and_the_current_year_in_progress(): void
    {
        TranscriptYear::create([
            'student_id' => $this->student->id, 'source' => TranscriptYear::EXTERNAL, 'year' => 2024, 'grade_label' => '2º ano',
            'school_name' => 'Escola Municipal Aurora', 'school_city' => 'João Pessoa', 'school_state' => 'PB', 'result' => TranscriptResult::Approved,
        ])->subjects()->create(['name' => 'Matemática', 'final_grade' => 8.5, 'workload_hours' => 160]);
        app(YearClosing::class)->closeEnrollment($this->enrollment2025, $this->secretary);

        $this->tab()
            ->callTableAction('issueTranscript', data: ['include_current_year' => true, 'observations' => 'Transferida em 15/06/2026.'])
            ->assertNotified('Documento emitido');

        $document = IssuedDocument::sole();
        $this->assertSame(IssuedDocumentType::SchoolTranscript, $document->type);
        $this->assertSame('HIS-2026-000001', $document->number);
        $this->assertTrue($document->documentable->is($this->student));
        $this->assertTrue($document->hasIntactContent());

        $years = $document->data['content']['years'];
        $this->assertSame([2024, 2025, 2026], array_column($years, 'year'));
        $this->assertSame(['Aprovado', 'Aprovado', 'Cursando'], array_column($years, 'result'));
        $this->assertSame('7,00 (parcial)', $years[2]['subjects'][0]['grade']);
        $this->assertSame(['name' => 'João Pereira', 'role' => 'Secretário(a) escolar · Portaria nº 12/2020'], $document->data['content']['second_signer']);

        $this->get(route('issued-documents.print', $document))
            ->assertOk()
            ->assertSee(['Histórico escolar', 'HIS-2026-000001', 'Ana Clara de Souza', 'Maria de Souza e Carlos de Souza', '2024 · 2º ano — Escola Municipal Aurora (João Pessoa/PB)'])
            ->assertSee(['2026 · 3º ano — Escola Ceci', 'Resultado: Cursando', 'Transferida em 15/06/2026.', 'Joana Albuquerque', 'João Pereira']);

        $pdf = $this->get(route('issued-documents.pdf', ['issuedDocument' => $document, 'download' => 1]))->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringContainsString('attachment; filename=historico-escolar-ana-clara-de-souza-his-2026-000001.pdf', $pdf->headers->get('Content-Disposition'));

        $this->assertStringContainsString('<strong>Resultado: Aprovado</strong>', view('pdf.issued-document', ['document' => $document])->render());

        auth()->logout();
        $this->get(route('documents.verify', $document->formattedCode()))
            ->assertOk()
            ->assertSee('Histórico escolar')
            ->assertSee('***.456.789-**')
            ->assertDontSee('123.456.789-09');
    }

    public function test_transcript_without_years_is_refused(): void
    {
        $this->enrollment2026->update(['status' => 'canceled']);

        $this->tab()
            ->callTableAction('issueTranscript', data: ['observations' => null])
            ->assertNotified('Documento não emitido');

        $this->assertSame(0, IssuedDocument::count());
        $this->expectException(ValidationException::class);
        SchoolTranscript::issue($this->student, $this->secretary, includeCurrentYear: false);
    }

    public function test_student_with_transcript_cannot_be_deleted_and_teachers_do_not_see_the_tab(): void
    {
        app(YearClosing::class)->closeEnrollment($this->enrollment2025, $this->secretary);

        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');
        $this->assertFalse(Gate::forUser($teacher)->allows('viewAny', TranscriptYear::class));

        $this->expectException(ValidationException::class);
        $this->student->delete();
    }
}
