<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * As turmas cadastradas antes da separação por ano são as de 2026: passam a pertencer ao ano letivo 2026.
 * Por segurança, só recebe 2026 a turma sem ano cujas matrículas são todas de 2026 (ou sem ano, legado);
 * uma turma com matrícula de outro ano fica como está, para revisão manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        $year2026 = DB::table('academic_years')->where('year', 2026)->orderBy('id')->value('id');

        if (! $year2026) {
            return;
        }

        $otherYears = DB::table('academic_years')->where('year', '!=', 2026)->pluck('id');

        DB::table('classrooms')
            ->whereNull('academic_year_id')
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('enrollments')
                ->whereColumn('enrollments.classroom_id', 'classrooms.id')
                ->whereIn('enrollments.academic_year_id', $otherYears))
            ->update(['academic_year_id' => $year2026]);
    }

    public function down(): void
    {
        // Sem volta automática: não há como saber quais turmas já tinham 2026 antes desta migração
    }
};
