<?php

namespace App\Policies;

use App\Models\Grade;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GradePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Teachers can view grades, coordinators and admins can view all
        return $user->hasAnyRole(['teacher', 'coordinator', 'admin']);
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

        // Coordinators and admins can view any grade
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Teachers, coordinators and admins can create grades
        return $user->hasAnyRole(['teacher', 'coordinator', 'admin']);
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

        // Coordinators and admins can edit any grade
        return $user->hasAnyRole(['coordinator', 'admin']);
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
