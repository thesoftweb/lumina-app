<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Level;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ClassroomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get Fundamental II level (should be seeded already)
        $fundamentalII = Level::where('name', 'Fundamental II')->first();

        if (!$fundamentalII) {
            // Fallback: create if doesn't exist
            $fundamentalII = Level::create([
                'name' => 'Fundamental II',
                'description' => 'Years 6-9 (6º to 9º ano)',
            ]);
        }

        // Create 3 classrooms for testing
        Classroom::create([
            'name' => '6º Ano A',
            'level_id' => $fundamentalII->id,
        ]);

        Classroom::create([
            'name' => '6º Ano B',
            'level_id' => $fundamentalII->id,
        ]);

        Classroom::create([
            'name' => '8º Ano A',
            'level_id' => $fundamentalII->id,
        ]);
    }
}
