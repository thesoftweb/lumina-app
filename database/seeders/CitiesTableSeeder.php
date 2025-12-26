<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CitiesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $fetchUrl = 'https://servicodados.ibge.gov.br/api/v1/localidades/municipios';

        $citiesData = json_decode(file_get_contents($fetchUrl), true);

        $cities = [];

        foreach ($citiesData as $city) {
            $stateId = null;

            if (isset($city['microrregiao']['mesorregiao']['UF']['id'])) {
                $stateId = $city['microrregiao']['mesorregiao']['UF']['id'];
            }

            // Skip cities without a valid state_id
            if ($stateId === null) {
                continue;
            }

            $cities[] = [
                'code' => $city['id'],
                'name' => $city['nome'],
                'state_id' => $stateId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('cities')->truncate();

        DB::table('cities')->insert($cities);
    }
}
