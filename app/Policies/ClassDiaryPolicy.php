<?php

namespace App\Policies;

use App\Models\ClassDiary;
use App\Models\User;
use App\Policies\Concerns\HandlesBulkActions;

class ClassDiaryPolicy
{
    use HandlesBulkActions;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Teachers, the school office (read-only) and admins can view class diaries
        return $user->hasAnyRole(['teacher', 'secretary', 'admin']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ClassDiary $classDiary): bool
    {
        // Teacher can view only their own diaries
        if ($user->hasRole('teacher')) {
            return $classDiary->teacher_id === $user->teacher?->id;
        }

        // The school office and admins can view any diary
        return $user->hasAnyRole(['secretary', 'admin']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Teachers and admins can create diaries
        return $user->hasAnyRole(['teacher', 'admin']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ClassDiary $classDiary): bool
    {
        // Teacher can update only their own diaries
        if ($user->hasRole('teacher')) {
            return $classDiary->teacher_id === $user->teacher?->id;
        }

        // Only admins can update any diary
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ClassDiary $classDiary): bool
    {
        // Teacher can delete only their own diaries
        if ($user->hasRole('teacher')) {
            return $classDiary->teacher_id === $user->teacher?->id;
        }

        // Only admins can delete diaries
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ClassDiary $classDiary): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ClassDiary $classDiary): bool
    {
        return $user->hasRole('admin');
    }
}
