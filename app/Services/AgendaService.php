<?php

namespace App\Services;

use App\Models\Agenda;
use App\Models\Classroom;
use Illuminate\Pagination\Paginator;

class AgendaService
{
    /**
     * Obter agendas para um aluno (baseado em sua turma)
     */
    public function getStudentAgendas(int $classroomId, bool $upcoming = false)
    {
        $query = Agenda::forClassroom($classroomId);

        if ($upcoming) {
            $query->upcoming();
        }

        return $query->get();
    }

    /**
     * Obter agendas para um aluno ao cômputo de pagina
     */
    public function getStudentAgendasPaginated(int $classroomId, int $perPage = 15, bool $upcoming = false)
    {
        $query = Agenda::forClassroom($classroomId);

        if ($upcoming) {
            $query->upcoming();
        }

        return $query->paginate($perPage);
    }

    /**
     * Criar nova agenda
     */
    public function create(array $data): Agenda
    {
        return Agenda::create($data);
    }

    /**
     * Atualizar agenda
     */
    public function update(Agenda $agenda, array $data): Agenda
    {
        $agenda->update($data);
        return $agenda->fresh();
    }

    /**
     * Deletar agenda
     */
    public function delete(Agenda $agenda): bool
    {
        return $agenda->delete();
    }

    /**
     * Obter agendas da turma específica (sem contar as globais)
     */
    public function getClassroomAgendas(int $classroomId, bool $upcoming = false)
    {
        $query = Agenda::where('classroom_id', $classroomId)
            ->where('global', false);

        if ($upcoming) {
            $query->upcoming();
        }

        return $query->get();
    }

    /**
     * Obter agendas globais
     */
    public function getGlobalAgendas(bool $upcoming = false)
    {
        $query = Agenda::where('global', true);

        if ($upcoming) {
            $query->upcoming();
        }

        return $query->get();
    }
}
