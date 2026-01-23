<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()['cache']->forget('spatie.permission.cache');

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
        ];

        foreach ($teacherPermissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create permissions for coordinators
        $coordinatorPermissions = [
            'view_all_classrooms',
            'view_all_grades',
            'view_all_teachers',
            'create_teacher',
            'edit_teacher',
            'view_reports',
        ];

        foreach ($coordinatorPermissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create admin permissions
        $adminPermissions = [
            'manage_users',
            'manage_roles',
            'manage_permissions',
            'view_all_data',
        ];

        foreach ($adminPermissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions
        $teacherRole = Role::create(['name' => 'teacher', 'guard_name' => 'web']);
        $teacherRole->givePermissionTo($teacherPermissions);

        $coordinatorRole = Role::create(['name' => 'coordinator', 'guard_name' => 'web']);
        $coordinatorRole->givePermissionTo(array_merge($teacherPermissions, $coordinatorPermissions));

        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo(Permission::all());
    }
}
