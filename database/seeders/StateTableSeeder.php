<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StateTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $fetchUrl = 'https://servicodados.ibge.gov.br/api/v1/localidades/estados';
        $statesData = json_decode(file_get_contents($fetchUrl), true);
        $states = [];
        foreach ($statesData as $state) {
            $states[] = [
                'code' => $state['id'],
                'name' => $state['nome'],
                'abbreviation' => $state['sigla'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('states')->truncate();

        DB::table('states')->insert($states);
    }
}
