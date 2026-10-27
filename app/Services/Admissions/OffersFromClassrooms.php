<?php

namespace App\Services\Admissions;

use App\Models\AcademicYear;
use App\Models\AdmissionOffer;
use App\Models\Classroom;
use Illuminate\Support\Collection;

/**
 * Cria uma vaga da pré-matrícula para cada turma do ano que ainda não tem vaga, para a família escolher a
 * turma na ficha. A quantidade de vagas fica em branco (opcional) e pode ser preenchida depois.
 */
class OffersFromClassrooms
{
    /** @return Collection<int, AdmissionOffer> vagas criadas */
    public function create(AcademicYear $year): Collection
    {
        $order = (int) AdmissionOffer::query()->where('academic_year_id', $year->getKey())->max('sort_order');

        return Classroom::query()
            ->where('academic_year_id', $year->getKey())
            ->whereNotIn('id', AdmissionOffer::query()->where('academic_year_id', $year->getKey())->whereNotNull('classroom_id')->select('classroom_id'))
            ->with('level')->get()
            // Na ficha, as turmas aparecem agrupadas por nível (ex.: Educação Infantil antes do Fundamental)
            ->sortBy(fn (Classroom $classroom): string => ($classroom->level?->name ?? '').'|'.$classroom->name)
            ->map(function (Classroom $classroom) use (&$order): AdmissionOffer {
                return AdmissionOffer::create([
                    ...AdmissionOffer::attributesFromClassroom($classroom),
                    'sort_order' => ++$order,
                    'is_active' => true,
                ]);
            })
            ->values();
    }
}
