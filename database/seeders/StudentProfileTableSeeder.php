<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\StudentProfile;
use Illuminate\Support\Facades\DB;

class StudentProfileTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $data = [
            ['name' => 'Altas Habilidades/Superdotação', 'is_active' => true],
            ['name' => 'Dificuldades especificas de aprendizagem', 'is_active' => true],
            ['name' => 'Dislexia', 'is_active' => true],
            ['name' => 'Discalculia', 'is_active' => true],
            ['name' => 'Disgrafia', 'is_active' => true],
            ['name' => 'Transtorno Especro Autista (TEA)', 'is_active' => true],
            ['name' => 'Transtorno Défice de Atenção e Hiperatividade (TDAH)', 'is_active' => true],
            ['name' => 'Transtorno de Ansiedade', 'is_active' => true],
            ['name' => 'Transtorno Opositivo Desafiador (TOD)', 'is_active' => true],
            ['name' => 'Transtorno de Humor', 'is_active' => true],
            ['name' => 'Deficiência Intelectual', 'is_active' => true],
            ['name' => 'Deficiência Física', 'is_active' => true],
            ['name' => 'Deficiência Auditiva', 'is_active' => true],
            ['name' => 'Deficiência Visual', 'is_active' => true],
            ['name' => 'Outros', 'is_active' => true],
        ];

        DB::table('student_profiles')->truncate();

        StudentProfile::insert($data);
    }
}
