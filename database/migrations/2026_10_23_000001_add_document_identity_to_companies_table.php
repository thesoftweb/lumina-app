<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dados que identificam a escola nos documentos emitidos (declarações, recibos, ficha de matrícula):
 * código INEP, endereço completo com cidade e quem assina pela instituição.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('inep_code', 8)->nullable()->after('tax_id');
            $table->string('address_number', 20)->nullable()->after('address');
            $table->string('neighborhood')->nullable()->after('address_number');
            $table->foreignId('city_id')->nullable()->after('neighborhood')->constrained('cities')->nullOnDelete();
            $table->string('postal_code', 9)->nullable()->after('city_id');
            $table->string('document_signer_name')->nullable()->after('logo_path');
            $table->string('document_signer_role')->nullable()->after('document_signer_name');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
            $table->dropColumn(['inep_code', 'address_number', 'neighborhood', 'postal_code', 'document_signer_name', 'document_signer_role']);
        });
    }
};
