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

        // Create permissions for the school office (secretaria): consulta o pedagógico, não altera
        $secretaryPermissions = [
            'view_all_classrooms',
            'view_all_grades',
            'view_all_teachers',
            'create_teacher',
            'edit_teacher',
            'view_reports',
            'view_all_students',
            'view_report_cards',
        ];

        foreach ($secretaryPermissions as $permission) {
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

        // Create roles and assign permissions (definição dos papéis em docs/NAVEGACAO_E_PERMISSOES.md)
        $teacherRole = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $teacherRole->syncPermissions($teacherPermissions);

        $secretaryRole = Role::firstOrCreate(['name' => 'secretary', 'guard_name' => 'web']);
        $secretaryRole->syncPermissions($secretaryPermissions);

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
