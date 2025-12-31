<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TermTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $insert = [
            ['name' => '1º Bimestre', 'start_date' => '2025-02-01', 'end_date' => '2025-03-31', 'year' => 2025, 'order' => 1],
            ['name' => '2º Bimestre', 'start_date' => '2025-04-01', 'end_date' => '2025-05-31', 'year' => 2025, 'order' => 2],
            ['name' => '3º Bimestre', 'start_date' => '2025-06-01', 'end_date' => '2025-07-31', 'year' => 2025, 'order' => 3],
            ['name' => '4º Bimestre', 'start_date' => '2025-08-01', 'end_date' => '2025-09-30', 'year' => 2025, 'order' => 4],
        ];

        // DB::table('terms')->truncate();

        DB::table('terms')->insert($insert);
    }
}
