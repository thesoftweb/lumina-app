<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Plan;
use App\Models\Student;
use App\Enums\EnrollmentStatus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EnrollmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get academic year (should exist from seeding)
        $academicYear = AcademicYear::first();
        if (!$academicYear) {
            $academicYear = AcademicYear::create([
                'year' => 2025,
                'description' => '2025',
                'start_at' => '2025-02-03',
                'end_at' => '2025-12-15',
            ]);
        }

        // Get plan (should exist from seeding)
        $plan = Plan::first();
        if (!$plan) {
            $plan = Plan::create([
                'name' => 'Plano Padrão',
                'base_amount' => 1000.00,
                'has_discount' => false,
                'discount_type' => 'none',
                'discount_value' => 0.00,
                'final_value' => 1000.00,
                'is_active' => true,
            ]);
        }

        // Get all classrooms and students
        $classrooms = Classroom::all();
        $students = Student::all();

        if ($classrooms->isEmpty() || $students->isEmpty()) {
            return; // Can't create enrollments without these
        }

        // Distribute students across classrooms
        $studentIndex = 0;
        foreach ($classrooms as $classroom) {
            // Enroll 2 students per classroom
            for ($i = 0; $i < 2 && $studentIndex < $students->count(); $i++) {
                $student = $students[$studentIndex++];

                Enrollment::create([
                    'student_id' => $student->id,
                    'classroom_id' => $classroom->id,
                    'enrollment_date' => now()->subMonths(1)->format('Y-m-d'),
                    'status' => 'active',
                    'academic_year' => $academicYear->id,
                    'plan_id' => $plan->id,
                ]);
            }
        }
    }
}
