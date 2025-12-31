<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubjectTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $insert = [
            ['name' => 'Matemática', 'code' => 'MAT101'],
            ['name' => 'Língua Portuguesa', 'code' => 'PORT101'],
            ['name' => 'Ciências', 'code' => 'CIE101'],
            ['name' => 'História', 'code' => 'HIST101'],
            ['name' => 'Geografia', 'code' => 'GEO101'],
            ['name' => 'Educação Física', 'code' => 'EF101'],
            ['name' => 'Artes', 'code' => 'ART101'],
            ['name' => 'Educação Financeira', 'code' => 'FIN101'],
            ['name' => 'Língua Estrangeira Moderna', 'code' => 'LEM101'],
        ];

        // DB::table('subjects')->truncate();

        DB::table('subjects')->insert($insert);
    }
}
