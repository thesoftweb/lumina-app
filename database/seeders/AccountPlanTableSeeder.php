<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountPlanTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('account_plans')->truncate();

        DB::table('account_plans')->insert([
            // -----------------------
            // ATIVO
            // -----------------------
            [
                'company_id' => null,
                'code'       => '1.1.01',
                'name'       => 'Caixa',
                'type'       => 'asset',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => null,
                'code'       => '1.1.02',
                'name'       => 'Conta Bancária',
                'type'       => 'asset',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => null,
                'code'       => '1.1.03',
                'name'       => 'Contas a Receber',
                'type'       => 'asset',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // -----------------------
            // PASSIVO
            // -----------------------
            [
                'company_id' => null,
                'code'       => '2.1.01',
                'name'       => 'Contas a Pagar',
                'type'       => 'liability',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => null,
                'code'       => '2.1.02',
                'name'       => 'Impostos a Recolher',
                'type'       => 'liability',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // -----------------------
            // RECEITAS
            // -----------------------
            [
                'company_id' => null,
                'code'       => '4.1.01',
                'name'       => 'Receita de Mensalidade',
                'type'       => 'income',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => null,
                'code'       => '4.1.02',
                'name'       => 'Receita de Matrícula',
                'type'       => 'income',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => null,
                'code'       => '4.1.03',
                'name'       => 'Venda de Materiais',
                'type'       => 'income',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // -----------------------
            // DESPESAS
            // -----------------------
            [
                'company_id' => null,
                'code'       => '5.1.01',
                'name'       => 'Despesa com Salários',
                'type'       => 'expense',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => null,
                'code'       => '5.1.02',
                'name'       => 'Despesa com Aluguel',
                'type'       => 'expense',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => null,
                'code'       => '5.1.03',
                'name'       => 'Despesa com Energia/Água',
                'type'       => 'expense',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => null,
                'code'       => '5.1.04',
                'name'       => 'Despesa com Material de Escritório',
                'type'       => 'expense',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
