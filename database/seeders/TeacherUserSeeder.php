<?php

namespace Database\Seeders;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TeacherUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a sample teacher user for testing
        $teacherUser = User::firstOrCreate(
            ['email' => 'professor@example.com'],
            [
                'name' => 'Professor Teste',
                'password' => bcrypt('password123'),
            ]
        );

        // Assign teacher role
        $teacherUser->assignRole('teacher');

        // Create or update associated Teacher record
        $teacher = Teacher::firstOrCreate(
            ['email' => 'professor@example.com'],
            [
                'name' => 'Professor Teste',
                'user_id' => $teacherUser->id,
                'date_of_birth' => '1990-01-15',
                'phone' => '(11) 98765-4321',
                'document_number' => '12345678900',
            ]
        );

        // If teacher already existed, update user_id if not set
        if (!$teacher->user_id) {
            $teacher->update(['user_id' => $teacherUser->id]);
        }

        // Create an admin user for testing
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password123'),
            ]
        );

        $adminUser->assignRole('admin');
    }
}
