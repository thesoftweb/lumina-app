<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base para boletim e histórico escolar: série e dias letivos da turma, carga horária da disciplina
 * na turma, fechamento de bimestre e o registro das correções de nota feitas pela secretaria.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('grade_label', 60)->nullable()->after('name')->comment('série/ano escolar, ex.: 3º ano');
            $table->unsignedSmallInteger('school_days')->nullable()->after('grade_label')->comment('dias letivos no ano');
        });

        Schema::table('classroom_subject', function (Blueprint $table) {
            $table->unsignedSmallInteger('workload_hours')->nullable()->comment('carga horária anual da disciplina na turma');
        });

        Schema::table('terms', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->comment('fechado: o professor não altera mais as notas');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('grade_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('classroom_subject_id')->constrained('classroom_subject')->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('before')->nullable();
            $table->json('after');
            $table->string('reason', 500);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_changes');

        Schema::table('terms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn('closed_at');
        });

        Schema::table('classroom_subject', fn (Blueprint $table) => $table->dropColumn('workload_hours'));

        Schema::table('classrooms', fn (Blueprint $table) => $table->dropColumn(['grade_label', 'school_days']));
    }
};
