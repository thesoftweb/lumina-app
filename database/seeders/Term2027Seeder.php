<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Bimestres de 2027. As datas abaixo são PROVISÓRIAS: ajustar ao calendário letivo
 * aprovado antes de abrir lançamentos. Idempotente (chave: ano + ordem).
 */
class Term2027Seeder extends Seeder
{
    public function run(): void
    {
        $terms = [
            ['order' => 1, 'name' => '1º Bimestre', 'start_date' => '2027-02-01', 'end_date' => '2027-04-30'],
            ['order' => 2, 'name' => '2º Bimestre', 'start_date' => '2027-05-03', 'end_date' => '2027-07-16'],
            ['order' => 3, 'name' => '3º Bimestre', 'start_date' => '2027-07-19', 'end_date' => '2027-09-30'],
            ['order' => 4, 'name' => '4º Bimestre', 'start_date' => '2027-10-01', 'end_date' => '2027-12-15'],
        ];

        foreach ($terms as $term) {
            $exists = DB::table('terms')->where('year', 2027)->where('order', $term['order'])->exists();

            if ($exists) {
                continue; // não sobrescreve datas ajustadas manualmente
            }

            DB::table('terms')->insert([
                'year' => 2027,
                'order' => $term['order'],
                'name' => $term['name'],
                'start_date' => $term['start_date'],
                'end_date' => $term['end_date'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
