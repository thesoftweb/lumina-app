<?php

namespace App\Policies;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TeacherPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Coordinators and admins can view all teachers
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Teacher $teacher): bool
    {
        // Teacher can view only their own profile
        if ($user->hasRole('teacher')) {
            return $user->teacher?->id === $teacher->id;
        }

        // Coordinators and admins can view any teacher
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only coordinators and admins can create teachers
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Teacher $teacher): bool
    {
        // Teacher can edit only their own profile
        if ($user->hasRole('teacher')) {
            return $user->teacher?->id === $teacher->id;
        }

        // Coordinators and admins can edit any teacher
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Teacher $teacher): bool
    {
        // Only admins can delete teachers
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Teacher $teacher): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Teacher $teacher): bool
    {
        return $user->hasRole('admin');
    }
}
