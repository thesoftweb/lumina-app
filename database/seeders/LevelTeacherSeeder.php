<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Teacher;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LevelTeacherSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the professor from TeacherUserSeeder
        $professor = Teacher::where('email', 'professor@example.com')->first();

        if (!$professor) {
            return; // If teacher doesn't exist, skip
        }

        // Get all classrooms
        $classrooms = Classroom::all();

        // Link professor to all classrooms
        foreach ($classrooms as $classroom) {
            DB::table('level_teacher')->insertOrIgnore([
                'teacher_id' => $professor->id,
                'level_id' => $classroom->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
