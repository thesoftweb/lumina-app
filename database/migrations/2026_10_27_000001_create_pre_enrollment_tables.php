<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pré-matrícula com link público: período de inscrição por ano letivo, vagas e valores ofertados no ano,
 * as fichas recebidas (leads) e o histórico de contatos da secretaria. Planos e turmas passam a poder
 * pertencer a um ano letivo; os já cadastrados ficam sem ano (legado) e continuam funcionando como hoje.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            $table->boolean('admissions_open')->default(false)->comment('ficha pública de pré-matrícula aceita inscrições');
            $table->date('admissions_starts_at')->nullable();
            $table->date('admissions_ends_at')->nullable();
            $table->text('admissions_notes')->nullable()->comment('texto exibido na ficha pública, ex.: documentos necessários');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });

        Schema::create('admission_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('level_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name')->comment('ex.: 3º ano - Fundamental I');
            $table->string('grade_label', 60)->nullable();
            $table->string('shift', 20)->nullable();
            $table->decimal('enrollment_fee', 10, 2)->nullable()->comment('taxa de matrícula divulgada');
            $table->unsignedSmallInteger('vacancies')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('pre_enrollments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique()->comment('protocolo exibido ao responsável');
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('admission_offer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('new');

            $table->string('student_name');
            $table->date('student_birth_date');
            $table->string('student_gender', 1)->nullable();
            $table->string('student_previous_school')->nullable();
            $table->boolean('student_has_special_needs')->default(false);
            $table->text('student_special_needs_notes')->nullable();

            $table->string('guardian_name');
            $table->string('guardian_document', 14)->comment('CPF só com dígitos');
            $table->string('guardian_kinship', 30)->nullable();
            $table->string('guardian_email')->nullable();
            $table->string('guardian_phone', 20);
            $table->string('postal_code', 9)->nullable();
            $table->string('address')->nullable();
            $table->string('address_number', 20)->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('city_name')->nullable();

            $table->text('message')->nullable();
            $table->string('source', 30)->nullable()->comment('como conheceu a escola');
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->timestamp('consent_at')->nullable()->comment('aceite LGPD na ficha pública');
            $table->string('ip_address', 45)->nullable();

            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_contact_at')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('lost_reason')->nullable();

            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('converted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['academic_year_id', 'status']);
            $table->index(['academic_year_id', 'guardian_document']);
        });

        Schema::create('pre_enrollment_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pre_enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20);
            $table->text('notes')->nullable();
            $table->string('status_after', 30)->nullable()->comment('situação da ficha depois do contato');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_enrollment_contacts');
        Schema::dropIfExists('pre_enrollments');
        Schema::dropIfExists('admission_offers');

        Schema::table('classrooms', fn (Blueprint $table) => $table->dropConstrainedForeignId('academic_year_id'));
        Schema::table('plans', fn (Blueprint $table) => $table->dropConstrainedForeignId('academic_year_id'));

        Schema::table('academic_years', fn (Blueprint $table) => $table->dropColumn([
            'admissions_open', 'admissions_starts_at', 'admissions_ends_at', 'admissions_notes',
        ]));
    }
};
