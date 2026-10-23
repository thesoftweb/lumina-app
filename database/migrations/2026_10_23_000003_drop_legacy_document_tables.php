<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Remove o módulo antigo de documentos livres (substituído por Documentos emitidos).
 * Se houver registros, eles são exportados antes para storage/app/private/backups, porque o down()
 * recria só a estrutura das tabelas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $backup = [];

        foreach (['documents', 'document_templates'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                $backup[$table] = DB::table($table)->get()->all();
            }
        }

        if ($backup !== []) {
            Storage::disk('local')->put(
                'backups/legacy-documents-'.now()->format('Y-m-d-His').'.json',
                json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            );
        }

        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_templates');
    }

    public function down(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('receipt');
            $table->longText('content');
            $table->json('merge_tags')->nullable();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_template_id')->constrained();
            $table->longText('content')->nullable();
            $table->json('data')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }
};
