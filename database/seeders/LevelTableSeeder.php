<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LevelTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $levels = [
            ['name' => 'Educação Infantil', 'is_active' => true],
            ['name' => 'Fundamental I', 'is_active' => true],
            ['name' => 'Fundamental II', 'is_active' => true],
            ['name' => 'Médio', 'is_active' => true],
            ['name' => 'Técnico', 'is_active' => true],
            ['name' => 'Superior', 'is_active' => true],
            ['name' => 'Pós-graduação', 'is_active' => true],
        ];

        DB::table('levels')->truncate();

        DB::table('levels')->insert($levels);
    }
}
