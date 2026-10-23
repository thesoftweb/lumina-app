<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Term2026Seeder extends Seeder
{
    public function run(): void
    {
        $terms = [
            ['order' => 1, 'name' => '1º Bimestre', 'start_date' => '2026-02-02', 'end_date' => '2026-04-30'],
            ['order' => 2, 'name' => '2º Bimestre', 'start_date' => '2026-05-01', 'end_date' => '2026-07-17'],
            ['order' => 3, 'name' => '3º Bimestre', 'start_date' => '2026-07-20', 'end_date' => '2026-09-30'],
            ['order' => 4, 'name' => '4º Bimestre', 'start_date' => '2026-10-01', 'end_date' => '2026-12-15'],
        ];

        foreach ($terms as $term) {
            DB::table('terms')->updateOrInsert(
                ['year' => 2026, 'order' => $term['order']],
                [
                    'name' => $term['name'],
                    'start_date' => $term['start_date'],
                    'end_date' => $term['end_date'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
}
