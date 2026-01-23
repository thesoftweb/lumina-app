<?php

namespace Database\Seeders;

use App\Models\State;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',\
        // ]);

        $this->call(RolePermissionSeeder::class);
        $this->call(SubjectTableSeeder::class);
        $this->call(TermTableSeeder::class);
        $this->call(AccountPlanTableSeeder::class);
        $this->call(StudentProfileTableSeeder::class);
        $this->call(StateTableSeeder::class);
        $this->call(CitiesTableSeeder::class);
        $this->call(TeacherUserSeeder::class);
        $this->call(ClassroomSeeder::class);
        $this->call(LevelTeacherSeeder::class);
        $this->call(CustomerSeeder::class);
        $this->call(StudentSeeder::class);
        $this->call(ClassroomSubjectSeeder::class);
        $this->call(EnrollmentSeeder::class);
    }
}
