<?php

namespace App\Services\Academic;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Plan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Monta as turmas do ano novo a partir das turmas de outro ano, sem tocar nas originais: copia nome, nível,
 * série, dias letivos, modo de avaliação e a grade de disciplinas com a carga horária. Os planos são os do
 * ano novo com o mesmo nome dos planos da turma original. Professores não são copiados: a atribuição muda
 * de um ano para o outro. Turma que já existe no ano novo com o mesmo nome é mantida (rodar de novo não duplica).
 */
class ClassroomYearCopier
{
    /**
     * @param  list<int>|null  $classroomIds  turmas do ano de origem a copiar (todas, se nulo)
     * @return array{created: Collection<int, Classroom>, skipped: list<string>, missing_plans: list<string>}
     */
    public function copy(AcademicYear $source, AcademicYear $target, ?array $classroomIds = null): array
    {
        if ($source->is($target)) {
            throw new \InvalidArgumentException('Escolha um ano de destino diferente do ano de origem.');
        }

        $targetPlans = Plan::query()->where('academic_year_id', $target->getKey())->get()->keyBy(fn (Plan $plan): string => mb_strtolower(trim($plan->name)));
        $existing = Classroom::query()->where('academic_year_id', $target->getKey())->pluck('name')->map(fn (string $name): string => mb_strtolower(trim($name)));

        $classrooms = Classroom::query()
            ->where('academic_year_id', $source->getKey())
            ->when($classroomIds !== null, fn ($query) => $query->whereKey($classroomIds))
            ->with(['plans', 'classroomSubjects'])
            ->get();

        $created = collect();
        $skipped = [];
        $missingPlans = [];

        DB::transaction(function () use ($classrooms, $target, $targetPlans, $existing, $created, &$skipped, &$missingPlans): void {
            foreach ($classrooms as $classroom) {
                if ($existing->contains(mb_strtolower(trim($classroom->name)))) {
                    $skipped[] = $classroom->name;

                    continue;
                }

                $copy = Classroom::create([
                    'academic_year_id' => $target->getKey(),
                    'name' => $classroom->name,
                    'grade_label' => $classroom->grade_label,
                    'school_days' => $classroom->school_days,
                    'level_id' => $classroom->level_id,
                    'assessment_mode' => $classroom->assessment_mode,
                ]);

                foreach ($classroom->classroomSubjects as $assignment) {
                    $copy->subjects()->attach($assignment->subject_id, ['workload_hours' => $assignment->workload_hours]);
                }

                foreach ($classroom->plans as $plan) {
                    $targetPlan = $targetPlans->get(mb_strtolower(trim($plan->name)));

                    $targetPlan
                        ? $copy->plans()->attach($targetPlan->getKey())
                        : $missingPlans[] = "{$plan->name} ({$classroom->name})";
                }

                $created->push($copy);
            }
        });

        return ['created' => $created, 'skipped' => $skipped, 'missing_plans' => $missingPlans];
    }
}
