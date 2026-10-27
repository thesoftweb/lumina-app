<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rematrícula pelo mesmo fluxo da pré-matrícula: a ficha sabe se é de aluno novo ou de quem já estuda na
 * escola, aponta para a matrícula anterior e pode ter um link próprio enviado à família. A matrícula
 * criada guarda a matrícula do ano anterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_enrollments', function (Blueprint $table) {
            $table->string('type', 20)->default('new')->after('status');
            $table->foreignId('previous_enrollment_id')->nullable()->after('type')->constrained('enrollments')->nullOnDelete();
            $table->string('access_token', 64)->nullable()->unique()->comment('link de rematrícula enviado à família');
            $table->timestamp('responded_at')->nullable()->comment('família confirmou pelo link de rematrícula');

            $table->index(['academic_year_id', 'type']);
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('previous_enrollment_id')->nullable()->constrained('enrollments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', fn (Blueprint $table) => $table->dropConstrainedForeignId('previous_enrollment_id'));

        Schema::table('pre_enrollments', function (Blueprint $table) {
            $table->dropIndex(['academic_year_id', 'type']);
            $table->dropConstrainedForeignId('previous_enrollment_id');
            $table->dropUnique(['access_token']);
            $table->dropColumn(['type', 'access_token', 'responded_at']);
        });
    }
};
