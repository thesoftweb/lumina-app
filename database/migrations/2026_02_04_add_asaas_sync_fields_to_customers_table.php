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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('asaas_sync_status')->default('pending')->comment('pending, synced, failed');
            $table->text('asaas_sync_error')->nullable()->comment('Motivo do erro de sincronização');
            $table->timestamp('asaas_synced_at')->nullable()->comment('Última sincronização com Asaas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['asaas_sync_status', 'asaas_sync_error', 'asaas_synced_at']);
        });
    }
};
