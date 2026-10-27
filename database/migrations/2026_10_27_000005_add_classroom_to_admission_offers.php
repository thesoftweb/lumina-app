<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A vaga da pré-matrícula pode ser uma turma do ano: a família escolhe a turma e a conversão já a traz marcada. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_offers', function (Blueprint $table) {
            $table->foreignId('classroom_id')->nullable()->after('level_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('admission_offers', fn (Blueprint $table) => $table->dropConstrainedForeignId('classroom_id'));
    }
};
