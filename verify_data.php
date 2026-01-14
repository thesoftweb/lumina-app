<?php

use App\Models\Teacher;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\Enrollment;
use App\Models\Attendance;
use App\Models\ClassDiary;
use App\Models\LessonPlan;

echo "=== DATABASE VERIFICATION ===\n";
echo "Teachers: " . Teacher::count() . "\n";
echo "Classrooms: " . Classroom::count() . "\n";
echo "Students: " . Student::count() . "\n";
echo "Enrollments: " . Enrollment::count() . "\n";
echo "Attendances: " . Attendance::count() . "\n";
echo "ClassDiaries: " . ClassDiary::count() . "\n";
echo "LessonPlans: " . LessonPlan::count() . "\n";
echo "\n=== TEACHER INFO ===\n";
$teacher = Teacher::first();
echo "Teacher: " . $teacher->name . " (ID: " . $teacher->id . ")\n";
echo "Associated Classrooms: " . $teacher->classrooms()->count() . "\n";
echo "\n=== CLASSROOM INFO ===\n";
foreach (Classroom::all() as $classroom) {
    echo "- $classroom->name\n";
    echo "  Students: " . $classroom->enrollments()->count() . "\n";
}
