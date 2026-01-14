<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AttendancePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Teachers, coordinators and admins can view attendance records
        return $user->hasAnyRole(['teacher', 'coordinator', 'admin']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Attendance $attendance): bool
    {
        // Teacher can view only attendance records they created
        if ($user->hasRole('teacher')) {
            return $attendance->teacher_id === $user->teacher?->id;
        }

        // Coordinators and admins can view any attendance record
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Teachers, coordinators and admins can create attendance records
        return $user->hasAnyRole(['teacher', 'coordinator', 'admin']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Attendance $attendance): bool
    {
        // Teacher can update only their own attendance records
        if ($user->hasRole('teacher')) {
            return $attendance->teacher_id === $user->teacher?->id;
        }

        // Coordinators and admins can update any attendance record
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Attendance $attendance): bool
    {
        // Teacher can delete only their own attendance records
        if ($user->hasRole('teacher')) {
            return $attendance->teacher_id === $user->teacher?->id;
        }

        // Only admins can delete attendance records
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Attendance $attendance): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Attendance $attendance): bool
    {
        return $user->hasRole('admin');
    }
}
