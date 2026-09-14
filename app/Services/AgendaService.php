<?php

namespace App\Services;

use App\Models\Schedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AgendaService
{
    /**
     * Obter agendas para um aluno (baseado em sua turma)
     */
    public function getStudentAgendas(int $classroomId, bool $upcoming = false)
    {
        $query = Schedule::forClassroom($classroomId);

        if ($upcoming) {
            $query->upcoming();
        }

        return $query->get();
    }

    public function getStudentAgendasForClassrooms(iterable $classroomIds, bool $upcoming = false): Collection
    {
        $classroomIds = collect($classroomIds)->filter()->unique()->values();

        if ($classroomIds->isEmpty()) {
            return collect();
        }

        $query = Schedule::query()
            ->forClassrooms($classroomIds)
            ->with('classrooms');

        if ($upcoming) {
            $query->upcoming();
        }

        return $query->get();
    }

    public function getStudentAgendasPaginatedForClassrooms(
        iterable $classroomIds,
        int $perPage = 15,
        bool $upcoming = false,
    ): LengthAwarePaginator {
        $classroomIds = collect($classroomIds)->filter()->unique()->values();
        $query = Schedule::query()->with('classrooms');

        if ($classroomIds->isEmpty()) {
            $query->whereRaw('1 = 0');
        } else {
            $query->forClassrooms($classroomIds);
        }

        if ($upcoming) {
            $query->upcoming();
        }

        return $query->paginate($perPage);
    }

    /**
     * Obter agendas para um aluno ao cômputo de pagina
     */
    public function getStudentAgendasPaginated(int $classroomId, int $perPage = 15, bool $upcoming = false)
    {
        $query = Schedule::forClassroom($classroomId);

        if ($upcoming) {
            $query->upcoming();
        }

        return $query->paginate($perPage);
    }

    /**
     * Criar nova agenda
     */
    public function create(array $data): Schedule
    {
        return Schedule::create($data);
    }

    /**
     * Atualizar agenda
     */
    public function update(Schedule $agenda, array $data): Schedule
    {
        $agenda->update($data);
        return $agenda->fresh();
    }

    /**
     * Deletar agenda
     */
    public function delete(Schedule $agenda): bool
    {
        return $agenda->delete();
    }

    /**
     * Obter agendas da turma específica (sem contar as globais)
     */
    public function getClassroomAgendas(int $classroomId, bool $upcoming = false)
    {
        $query = Schedule::where('global', false)
            ->where(function ($query) use ($classroomId) {
                $query->whereHas('classrooms', fn ($query) => $query->whereKey($classroomId))
                    ->orWhere('classroom_id', $classroomId);
            });

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
        $query = Schedule::where('global', true);

        if ($upcoming) {
            $query->upcoming();
        }

        return $query->get();
    }
}
