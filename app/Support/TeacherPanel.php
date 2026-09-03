<?php

namespace App\Support;

use App\Models\Classroom;
use App\Models\ClassroomSubject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class TeacherPanel
{
    public static function teacher(User $user): Teacher
    {
        $teacher = $user->teacher;

        if (! $teacher) {
            throw ValidationException::withMessages([
                'teacher' => 'O usuário não possui um professor vinculado.',
            ]);
        }

        return $teacher;
    }

    public static function classrooms(User $user): Collection
    {
        return self::teacher($user)->classrooms()->with('level')->orderBy('name')->get();
    }

    public static function ensureClassroom(User $user, int $classroomId): Classroom
    {
        return self::teacher($user)->classrooms()->whereKey($classroomId)->firstOrFail();
    }

    public static function classroomSubjects(User $user, ?int $classroomId = null): Builder
    {
        return ClassroomSubject::query()
            ->with(['classroom', 'subject'])
            ->whereHas('teachers', fn (Builder $query) => $query->whereKey(self::teacher($user)->id))
            ->when($classroomId, fn (Builder $query) => $query->where('classroom_id', $classroomId));
    }

    public static function ensureClassroomSubject(User $user, int $classroomSubjectId): ClassroomSubject
    {
        return self::classroomSubjects($user)->whereKey($classroomSubjectId)->firstOrFail();
    }
}
