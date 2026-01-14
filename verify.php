#!/usr/bin/env php
<?php

use App\Models\Teacher;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\Enrollment;

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DATABASE VERIFICATION ===\n";
echo "Teachers: " . Teacher::count() . "\n";
echo "Classrooms: " . Classroom::count() . "\n";
echo "Students: " . Student::count() . "\n";
echo "Enrollments: " . Enrollment::count() . "\n";

$teacher = Teacher::first();
if ($teacher) {
    echo "\n=== TEACHER INFO ===\n";
    echo "Name: " . $teacher->name . "\n";
    echo "Email: " . $teacher->email . "\n";
    echo "Classrooms: " . $teacher->classrooms()->count() . "\n";
}

echo "\n=== CLASSROOM INFO ===\n";
foreach (Classroom::all() as $classroom) {
    echo "- " . $classroom->name . " (Enrollments: " . $classroom->enrollments()->count() . ")\n";
}
