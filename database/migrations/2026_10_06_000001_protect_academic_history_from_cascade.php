<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Notas e frequências são histórico acadêmico: apagar matrícula, disciplina da turma,
 * turma ou professor não pode apagá-las em cascata. Passa a ser bloqueado (RESTRICT).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table): void {
            $table->dropForeign(['enrollment_id']);
            $table->dropForeign(['classroom_subject_id']);
        });

        Schema::table('grades', function (Blueprint $table): void {
            $table->foreign('enrollment_id')->references('id')->on('enrollments')->restrictOnDelete();
            $table->foreign('classroom_subject_id')->references('id')->on('classroom_subject')->restrictOnDelete();
        });

        // term_id nunca teve FK. Só é criada se não houver valores órfãos.
        $orphans = DB::table('grades')
            ->whereNotNull('term_id')
            ->whereNotIn('term_id', DB::table('terms')->select('id'))
            ->count();

        if ($orphans === 0) {
            Schema::table('grades', function (Blueprint $table): void {
                $table->foreign('term_id')->references('id')->on('terms')->restrictOnDelete();
            });
        } else {
            Log::warning("grades.term_id: FK não criada, há {$orphans} notas com bimestre inexistente.");
        }

        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropForeign(['enrollment_id']);
            $table->dropForeign(['classroom_id']);
            $table->dropForeign(['teacher_id']);
        });

        Schema::table('attendances', function (Blueprint $table): void {
            $table->foreign('enrollment_id')->references('id')->on('enrollments')->restrictOnDelete();
            $table->foreign('classroom_id')->references('id')->on('classrooms')->restrictOnDelete();
            $table->foreign('teacher_id')->references('id')->on('teachers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropForeign(['enrollment_id']);
            $table->dropForeign(['classroom_id']);
            $table->dropForeign(['teacher_id']);
        });

        Schema::table('attendances', function (Blueprint $table): void {
            $table->foreign('enrollment_id')->references('id')->on('enrollments')->cascadeOnDelete();
            $table->foreign('classroom_id')->references('id')->on('classrooms')->cascadeOnDelete();
            $table->foreign('teacher_id')->references('id')->on('teachers')->cascadeOnDelete();
        });

        if (Schema::hasColumn('grades', 'term_id')) {
            try {
                Schema::table('grades', fn (Blueprint $table) => $table->dropForeign(['term_id']));
            } catch (\Throwable) {
                // FK não havia sido criada (notas órfãs).
            }
        }

        Schema::table('grades', function (Blueprint $table): void {
            $table->dropForeign(['enrollment_id']);
            $table->dropForeign(['classroom_subject_id']);
        });

        Schema::table('grades', function (Blueprint $table): void {
            $table->foreign('enrollment_id')->references('id')->on('enrollments')->cascadeOnDelete();
            $table->foreign('classroom_subject_id')->references('id')->on('classroom_subject')->cascadeOnDelete();
        });
    }
};
