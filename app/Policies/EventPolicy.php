<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['teacher', 'coordinator', 'admin']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Event $event): bool
    {
        // Teachers can view events of their classrooms
        if ($user->hasRole('teacher')) {
            return $event->classroom->teachers()->where('teacher_id', $user->teacher?->id)->exists();
        }

        // Coordinators and admins can view any event
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only coordinators and admins can create events
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Event $event): bool
    {
        // Only coordinators and admins can update events
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Event $event): bool
    {
        // Only admins can delete events
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Event $event): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Event $event): bool
    {
        return $user->hasRole('admin');
    }
}
