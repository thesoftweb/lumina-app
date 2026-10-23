<?php

namespace App\Policies;

use App\Models\Grade;
use App\Models\User;
use App\Policies\Concerns\HandlesBulkActions;

class GradePolicy
{
    use HandlesBulkActions;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Teachers can view grades, the school office (read-only) and admins can view all
        return $user->hasAnyRole(['teacher', 'secretary', 'admin']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Grade $grade): bool
    {
        // Teacher can view only grades they created
        if ($user->hasRole('teacher')) {
            return $grade->teacher_id === $user->teacher?->id;
        }

        // The school office and admins can view any grade
        return $user->hasAnyRole(['secretary', 'admin']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Professores lançam pelo diário; a secretaria lança o que faltou e corrige
        return $user->hasAnyRole(['teacher', 'secretary', 'admin']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Grade $grade): bool
    {
        // Teacher can edit only their own grades
        if ($user->hasRole('teacher')) {
            return $grade->teacher_id === $user->teacher?->id;
        }

        // A secretaria e o admin corrigem qualquer nota (com motivo, em grade_changes)
        return $user->hasAnyRole(['secretary', 'admin']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Grade $grade): bool
    {
        // Teacher can delete only their own grades
        if ($user->hasRole('teacher')) {
            return $grade->teacher_id === $user->teacher?->id;
        }

        // Only admins can force delete grades
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Grade $grade): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Grade $grade): bool
    {
        return $user->hasRole('admin');
    }
}
