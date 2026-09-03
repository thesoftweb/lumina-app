<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Create permissions for teachers
        $teacherPermissions = [
            'view_own_classroom',
            'view_own_grades',
            'create_grade',
            'edit_own_grade',
            'view_own_attendance',
            'create_attendance',
            'view_own_class_diary',
            'create_class_diary',
            'view_own_lesson_plan',
            'create_lesson_plan',
            'view_assigned_students',
            'view_birthdays',
            'view_report_cards',
            'edit_own_attendance',
            'edit_own_class_diary',
            'edit_own_lesson_plan',
        ];

        foreach ($teacherPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create permissions for coordinators
        $coordinatorPermissions = [
            'view_all_classrooms',
            'view_all_grades',
            'view_all_teachers',
            'create_teacher',
            'edit_teacher',
            'view_reports',
            'view_all_students',
            'manage_attendance',
            'manage_class_diaries',
            'manage_lesson_plans',
        ];

        foreach ($coordinatorPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create admin permissions
        $adminPermissions = [
            'manage_users',
            'manage_roles',
            'manage_permissions',
            'view_all_data',
        ];

        foreach ($adminPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions
        $teacherRole = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $teacherRole->syncPermissions($teacherPermissions);

        $coordinatorRole = Role::firstOrCreate(['name' => 'coordinator', 'guard_name' => 'web']);
        $coordinatorRole->syncPermissions(array_merge($teacherPermissions, $coordinatorPermissions));

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
