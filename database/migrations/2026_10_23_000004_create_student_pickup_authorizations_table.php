<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pessoas autorizadas a buscar o aluno na escola (cada uma recebe uma carteirinha) e a foto do aluno,
 * impressa na carteirinha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('name');
        });

        Schema::create('student_pickup_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('document', 30)->nullable();
            $table->string('relationship', 60);
            $table->string('phone', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_pickup_authorizations');

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
