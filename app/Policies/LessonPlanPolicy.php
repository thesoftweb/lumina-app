<?php

namespace App\Policies;

use App\Models\LessonPlan;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LessonPlanPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Teachers, coordinators and admins can view lesson plans
        return $user->hasAnyRole(['teacher', 'coordinator', 'admin']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, LessonPlan $lessonPlan): bool
    {
        // Teacher can view only their own lesson plans
        if ($user->hasRole('teacher')) {
            return $lessonPlan->teacher_id === $user->teacher?->id;
        }

        // Coordinators and admins can view any lesson plan
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Teachers, coordinators and admins can create lesson plans
        return $user->hasAnyRole(['teacher', 'coordinator', 'admin']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LessonPlan $lessonPlan): bool
    {
        // Teacher can update only their own lesson plans
        if ($user->hasRole('teacher')) {
            return $lessonPlan->teacher_id === $user->teacher?->id;
        }

        // Coordinators and admins can update any lesson plan
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LessonPlan $lessonPlan): bool
    {
        // Teacher can delete only their own lesson plans
        if ($user->hasRole('teacher')) {
            return $lessonPlan->teacher_id === $user->teacher?->id;
        }

        // Only admins can delete lesson plans
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, LessonPlan $lessonPlan): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, LessonPlan $lessonPlan): bool
    {
        return $user->hasRole('admin');
    }
}
