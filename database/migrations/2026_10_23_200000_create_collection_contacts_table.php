<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cobrança de inadimplentes: regras de multa e juros (as mesmas do Asaas, para estimar o valor
 * atualizado), modelo da mensagem de cobrança e o histórico de contatos com o responsável.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->decimal('late_fine_percent', 5, 2)->nullable()->comment('multa por atraso, % sobre o valor');
            $table->decimal('late_interest_monthly_percent', 5, 2)->nullable()->comment('juros por atraso, % ao mês, proporcional por dia');
            $table->text('collection_message')->nullable()->comment('modelo da mensagem de cobrança; vazio usa o padrão');
        });

        // Regras configuradas hoje no Asaas
        DB::table('companies')->update(['late_fine_percent' => 2, 'late_interest_monthly_percent' => 6]);

        Schema::create('collection_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20);
            $table->string('outcome', 30);
            $table->text('notes')->nullable();
            $table->date('promised_date')->nullable()->index();
            $table->decimal('promised_amount', 10, 2)->nullable();
            $table->decimal('overdue_total', 10, 2)->comment('saldo vencido no momento do contato');
            $table->json('invoice_ids')->comment('faturas vencidas no momento do contato');
            $table->text('message')->nullable()->comment('mensagem enviada, quando houver');
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_contacts');

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['late_fine_percent', 'late_interest_monthly_percent', 'collection_message']);
        });
    }
};
