<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Subject extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (Subject $subject): void {
            $hasHistory = Grade::query()
                ->whereIn('classroom_subject_id', ClassroomSubject::query()->where('subject_id', $subject->id)->select('id'))
                ->exists()
                || DB::table('class_diaries')->where('subject_id', $subject->id)->exists()
                || DB::table('lesson_plans')->where('subject_id', $subject->id)->exists();

            if ($hasHistory) {
                throw ValidationException::withMessages([
                    'subject' => 'Esta disciplina possui notas, diários ou planos de aula e não pode ser excluída.',
                ]);
            }
        });
    }

    public function classrooms()
    {
        return $this->belongsToMany(Classroom::class)->using(ClassroomSubject::class);
    }
}
