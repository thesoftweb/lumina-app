<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Subject;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClassroomSubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get subjects (should be seeded already)
        $subjects = Subject::limit(3)->get();

        if ($subjects->isEmpty()) {
            // Create basic subjects if they don't exist
            $subjects = [
                Subject::firstOrCreate(['name' => 'Português']),
                Subject::firstOrCreate(['name' => 'Matemática']),
                Subject::firstOrCreate(['name' => 'Ciências']),
            ];
        }

        // Get all classrooms
        $classrooms = Classroom::all();

        // Link each classroom to subjects
        foreach ($classrooms as $classroom) {
            foreach ($subjects as $subject) {
                DB::table('classroom_subject')->insertOrIgnore([
                    'classroom_id' => $classroom->id,
                    'subject_id' => $subject->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
