<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('enrollments', 'academic_year') && ! Schema::hasColumn('enrollments', 'academic_year_id')) {
            Schema::table('enrollments', fn (Blueprint $table) => $table->renameColumn('academic_year', 'academic_year_id'));
        }

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
        });

        Schema::create('classroom_subject_teacher', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('classroom_subject_id')->constrained('classroom_subject')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['classroom_subject_id', 'teacher_id'], 'classroom_subject_teacher_unique');
        });

        $this->removeDuplicateGrades();
        $this->removeDuplicateAttendances();

        Schema::table('grades', function (Blueprint $table): void {
            $table->unique(['enrollment_id', 'classroom_subject_id', 'term_id'], 'grades_enrollment_subject_term_unique');
        });

        Schema::table('attendances', function (Blueprint $table): void {
            $table->unique(['enrollment_id', 'classroom_id', 'date'], 'attendances_enrollment_classroom_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', fn (Blueprint $table) => $table->dropUnique('attendances_enrollment_classroom_date_unique'));
        Schema::table('grades', fn (Blueprint $table) => $table->dropUnique('grades_enrollment_subject_term_unique'));
        Schema::dropIfExists('classroom_subject_teacher');

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropForeign(['academic_year_id']);
            $table->renameColumn('academic_year_id', 'academic_year');
        });
    }

    private function removeDuplicateGrades(): void
    {
        DB::table('grades')
            ->select('enrollment_id', 'classroom_subject_id', 'term_id', DB::raw('MAX(id) as keep_id'))
            ->groupBy('enrollment_id', 'classroom_subject_id', 'term_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function (object $duplicate): void {
                DB::table('grades')
                    ->where('enrollment_id', $duplicate->enrollment_id)
                    ->where('classroom_subject_id', $duplicate->classroom_subject_id)
                    ->where('term_id', $duplicate->term_id)
                    ->where('id', '!=', $duplicate->keep_id)
                    ->delete();
            });
    }

    private function removeDuplicateAttendances(): void
    {
        // Registros excluídos não podem coexistir com a chave única usada pela chamada reabrível.
        DB::table('attendances')->whereNotNull('deleted_at')->delete();

        DB::table('attendances')
            ->select('enrollment_id', 'classroom_id', 'date', DB::raw('MAX(id) as keep_id'))
            ->groupBy('enrollment_id', 'classroom_id', 'date')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function (object $duplicate): void {
                DB::table('attendances')
                    ->where('enrollment_id', $duplicate->enrollment_id)
                    ->where('classroom_id', $duplicate->classroom_id)
                    ->whereDate('date', $duplicate->date)
                    ->where('id', '!=', $duplicate->keep_id)
                    ->delete();
            });
    }
};
