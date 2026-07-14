<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['coordinator', 'admin'])
            || ($user->hasRole('teacher') && $user->can('view_assigned_students'));
    }

    public function view(User $user, Student $student): bool
    {
        if ($user->hasRole('teacher')) {
            return $user->can('view_assigned_students') && $student->enrollments()
                ->whereHas('classroom.teachers', fn ($query) => $query->whereKey($user->teacher?->id))
                ->exists();
        }

        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    public function update(User $user, Student $student): bool
    {
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->hasRole('admin');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
