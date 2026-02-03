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
            $table->text('invoice_link')->nullable()->after('asaas_synced_at')->comment('Link da fatura');
            $table->longText('invoice_qrcode')->nullable()->after('invoice_link')->comment('QR Code da fatura (base64 ou SVG)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['invoice_link', 'invoice_qrcode']);
        });
    }
};
