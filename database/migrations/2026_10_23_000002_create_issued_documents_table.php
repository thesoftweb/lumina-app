<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentos emitidos pela escola (declarações e recibos): numerados por série e ano, com código de
 * verificação público e o conteúdo congelado no momento da emissão. Nunca são alterados nem excluídos;
 * para corrigir, cancela-se e emite-se outro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('series', 3);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['series', 'year']);
        });

        Schema::create('issued_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50)->index();
            $table->string('series', 3);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('sequence');
            $table->string('number', 20)->unique();
            $table->string('verification_code', 16)->unique();
            $table->nullableMorphs('documentable');
            $table->string('subject');
            $table->string('purpose', 150)->nullable();
            $table->date('valid_until')->nullable();
            $table->json('data');
            $table->char('content_hash', 64);
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamp('canceled_at')->nullable();
            $table->foreignId('canceled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['series', 'year', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issued_documents');
        Schema::dropIfExists('document_sequences');
    }
};
