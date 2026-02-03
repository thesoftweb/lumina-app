<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('asaas_invoice_id')->nullable()->index()->comment('ID da cobrança no Asaas');
            $table->enum('asaas_sync_status', ['pending', 'synced', 'canceled', 'failed'])->nullable()->comment('Status de sincronização com Asaas');
            $table->timestamp('asaas_synced_at')->nullable()->comment('Última sincronização com Asaas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['asaas_invoice_id', 'asaas_sync_status', 'asaas_synced_at']);
        });
    }
};
