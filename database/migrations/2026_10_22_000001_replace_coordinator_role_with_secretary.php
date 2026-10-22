<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * O sistema passa a ter três papéis: admin, secretary e teacher.
 * Quem era coordenador vira secretaria, para não perder o acesso ao painel.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->moveUsers(from: 'coordinator', to: 'secretary');
    }

    public function down(): void
    {
        $this->moveUsers(from: 'secretary', to: 'coordinator');
    }

    private function moveUsers(string $from, string $to): void
    {
        $roles = config('permission.table_names.roles');
        $pivot = config('permission.table_names.model_has_roles');
        $roleKey = config('permission.column_names.role_pivot_key') ?? 'role_id';
        $modelKey = config('permission.column_names.model_morph_key');

        $oldId = DB::table($roles)->where(['name' => $from, 'guard_name' => 'web'])->value('id');

        if (! $oldId) {
            return;
        }

        $newId = DB::table($roles)->where(['name' => $to, 'guard_name' => 'web'])->value('id')
            ?? DB::table($roles)->insertGetId(['name' => $to, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);

        foreach (DB::table($pivot)->where($roleKey, $oldId)->get() as $assignment) {
            DB::table($pivot)->updateOrInsert([
                $roleKey => $newId,
                $modelKey => $assignment->{$modelKey},
                'model_type' => $assignment->model_type,
            ]);
        }

        DB::table($pivot)->where($roleKey, $oldId)->delete();
        DB::table(config('permission.table_names.role_has_permissions'))->where($roleKey, $oldId)->delete();
        DB::table($roles)->where('id', $oldId)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
