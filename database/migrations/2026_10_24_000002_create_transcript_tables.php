<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vida escolar do aluno para o histórico: um registro por ano/série cursado, nesta escola (gerado ao
 * fechar o ano letivo, a partir da matrícula) ou em outra (lançado a partir da documentação de
 * transferência), com as disciplinas, notas ou conceitos e carga horária.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->decimal('minimum_attendance_percent', 5, 2)->default(75)->comment('frequência mínima para aprovação (LDB: 75%)');
            $table->string('school_secretary_name')->nullable()->comment('secretário(a) escolar que assina o histórico');
            $table->string('school_secretary_registration')->nullable()->comment('registro/autorização do secretário escolar');
        });

        Schema::create('transcript_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->nullable()->unique()->constrained()->restrictOnDelete()->comment('ano cursado nesta escola');
            $table->string('source', 20)->comment('internal: desta escola; external: de outra escola');
            $table->unsignedSmallInteger('year');
            $table->string('grade_label', 60)->comment('série/ano escolar');
            $table->string('school_name');
            $table->string('school_city')->nullable();
            $table->string('school_state', 2)->nullable();
            $table->string('assessment_mode', 20)->default('numeric');
            $table->decimal('attendance_percent', 5, 2)->nullable();
            $table->unsignedSmallInteger('school_days')->nullable();
            $table->unsignedSmallInteger('total_workload')->nullable();
            $table->string('result', 30);
            $table->string('computed_result', 30)->nullable()->comment('resultado calculado pelo sistema (anos desta escola)');
            $table->string('adjustment_reason', 500)->nullable()->comment('motivo quando o resultado difere do calculado');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'year']);
        });

        Schema::create('transcript_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transcript_year_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('final_grade', 5, 2)->nullable();
            $table->string('concept', 20)->nullable();
            $table->unsignedSmallInteger('workload_hours')->nullable();
            $table->string('result', 20)->nullable()->comment('approved, failed ou pending');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_subjects');
        Schema::dropIfExists('transcript_years');

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['minimum_attendance_percent', 'school_secretary_name', 'school_secretary_registration']);
        });
    }
};
