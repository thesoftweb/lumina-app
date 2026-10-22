<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;
use App\Policies\Concerns\HandlesBulkActions;

class AttendancePolicy
{
    use HandlesBulkActions;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Teachers, the school office (read-only) and admins can view attendance records
        return $user->hasAnyRole(['teacher', 'secretary', 'admin']);
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

        // The school office and admins can view any attendance record
        return $user->hasAnyRole(['secretary', 'admin']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Teachers and admins can create attendance records
        return $user->hasAnyRole(['teacher', 'admin']);
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

        // Only admins can update any attendance record
        return $user->hasRole('admin');
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
