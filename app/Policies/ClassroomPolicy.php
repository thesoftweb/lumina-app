<?php

namespace App\Policies;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ClassroomPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Teachers can view classrooms, coordinators and admins can view all
        return $user->hasAnyRole(['teacher', 'coordinator', 'admin']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Classroom $classroom): bool
    {
        // Teacher can view only classrooms they teach
        if ($user->hasRole('teacher')) {
            return $classroom->teachers()->where('teacher_id', $user->teacher?->id)->exists();
        }

        // Coordinators and admins can view any classroom
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only coordinators and admins can create classrooms
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Classroom $classroom): bool
    {
        // Only coordinators and admins can edit classrooms
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Classroom $classroom): bool
    {
        // Only admins can delete classrooms
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Classroom $classroom): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Classroom $classroom): bool
    {
        return $user->hasRole('admin');
    }
}
