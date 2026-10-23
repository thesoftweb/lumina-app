<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\Classroom;
use App\Services\AgendaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AgendaVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_agendas_are_visible_globally_or_to_any_selected_classroom(): void
    {
        [$firstClassroom, $secondClassroom, $otherClassroom, $companyId] = $this->schoolRecords();

        $global = $this->agenda('Comunicado geral', true, $companyId);
        $selected = $this->agenda('Agenda das duas turmas', false, $companyId);
        $selected->classrooms()->attach([$firstClassroom->id, $secondClassroom->id]);
        $hidden = $this->agenda('Agenda de outra turma', false, $companyId);
        $hidden->classrooms()->attach($otherClassroom->id);

        $agendas = app(AgendaService::class)
            ->getStudentAgendasForClassrooms([$firstClassroom->id]);

        $this->assertEqualsCanonicalizing(
            [$global->id, $selected->id],
            $agendas->pluck('id')->all(),
        );
    }

    public function test_main_student_portal_displays_only_applicable_agendas(): void
    {
        [$classroom, , $otherClassroom, $companyId] = $this->schoolRecords();
        $customerId = DB::table('customers')->insertGetId([
            'name' => 'Responsável',
            'email' => 'responsavel@example.test',
            'document' => '12345678901',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $studentId = DB::table('students')->insertGetId([
            'customer_id' => $customerId,
            'name' => 'Aluno Teste',
            'date_of_birth' => '2015-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('enrollments')->insert([
            'student_id' => $studentId,
            'customer_id' => $customerId,
            'classroom_id' => $classroom->id,
            'enrollment_date' => now()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $visible = $this->agenda('Passeio da turma', false, $companyId);
        $visible->update([
            'date' => now()->startOfMonth()->addDay(),
            'description' => '<p>Levar autorização assinada.</p>',
        ]);
        $visible->classrooms()->attach($classroom->id);
        $distant = $this->agenda('Evento mais distante', false, $companyId);
        $distant->update(['date' => now()->subYear()->endOfMonth()->subDay()]);
        $distant->classrooms()->attach($classroom->id);
        $outsideMonth = $this->agenda('Evento do próximo mês', false, $companyId);
        $outsideMonth->update(['date' => now()->addMonthNoOverflow()->startOfMonth()]);
        $outsideMonth->classrooms()->attach($classroom->id);
        $hidden = $this->agenda('Recado de outra turma', false, $companyId);
        $hidden->classrooms()->attach($otherClassroom->id);

        $this->withSession(['customer_document' => '12345678901'])
            ->get('/portal/aluno')
            ->assertOk()
            ->assertSee('Agenda')
            ->assertDontSee('Passeio da turma');

        $this->withSession(['customer_document' => '12345678901'])
            ->get('/portal/agendas')
            ->assertOk()
            ->assertSee('Voltar para o início')
            ->assertSee('positive.png')
            ->assertSeeInOrder(['Evento mais distante', 'Passeio da turma', 'Evento do próximo mês'])
            ->assertSee(now()->startOfMonth()->addDay()->format('d/m'))
            ->assertSee(route('portal.agendas.show', $visible->id))
            ->assertDontSee('Recado de outra turma');

        $this->withSession(['customer_document' => '12345678901'])
            ->get(route('portal.agendas.show', $visible->id))
            ->assertOk()
            ->assertSee('Voltar para agendas')
            ->assertSee('positive.png')
            ->assertSee('Passeio da turma')
            ->assertSee('Levar autorização assinada.');

        $this->withSession(['customer_document' => '12345678901'])
            ->get(route('portal.agendas.show', $hidden->id))
            ->assertNotFound();

        $this->withSession(['customer_document' => '12345678901'])
            ->get('/portal/faturas')
            ->assertOk()
            ->assertSee('Voltar para o início')
            ->assertSee('positive.png')
            ->assertSee('Faturas Disponíveis');

        $this->withSession(['customer_document' => '12345678901'])
            ->get('/portal/matriculas')
            ->assertOk()
            ->assertSee('Voltar para o início')
            ->assertSee('positive.png')
            ->assertSee('Matrículas Ativas');
    }

    private function schoolRecords(): array
    {
        $companyId = DB::table('companies')->insertGetId([
            'name' => 'Escola Teste',
            'email' => 'escola@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $levelId = DB::table('levels')->insertGetId([
            'name' => 'Fundamental',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classrooms = collect(['1º A', '1º B', '2º A'])->map(function (string $name) use ($levelId) {
            return Classroom::create(['name' => $name, 'level_id' => $levelId]);
        });

        return [
            $classrooms[0],
            $classrooms[1],
            $classrooms[2],
            $companyId,
        ];
    }

    private function agenda(string $title, bool $global, int $companyId): Schedule
    {
        return Schedule::create([
            'title' => $title,
            'date' => now()->addDay(),
            'global' => $global,
            'company_id' => $companyId,
        ]);
    }
}
