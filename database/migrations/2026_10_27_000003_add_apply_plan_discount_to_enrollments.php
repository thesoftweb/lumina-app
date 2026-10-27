<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O desconto do plano deixa de valer sozinho: ele é negociado e marcado na matrícula ao efetivá-la.
 * Matrículas existentes ficam sem a marca; como nenhum plano tinha has_discount ligado, a cobrança delas
 * não muda. plans.discount_value ganha padrão 0 para planos sem desconto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->boolean('apply_plan_discount')->default(false)->comment('desconto do plano negociado na matrícula');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('discount_value', 10, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('discount_value', 10, 2)->change();
        });

        Schema::table('enrollments', fn (Blueprint $table) => $table->dropColumn('apply_plan_discount'));
    }
};
