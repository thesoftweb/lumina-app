<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vencimento da cobrança no Asaas quando difere do vencimento da fatura: o Asaas não aceita cobrança
 * com vencimento passado, então a fatura gerada depois do vencimento vai para o Asaas vencendo hoje e
 * guarda aqui essa data, mantendo o vencimento original na fatura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->date('asaas_due_date')->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('asaas_due_date');
        });
    }
};
