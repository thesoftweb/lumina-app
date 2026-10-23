<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Validation\ValidationException;

class ClassroomSubject extends Pivot
{
    public $table = 'classroom_subject';

    public $fillable = [
        'classroom_id',
        'subject_id',
    ];

    protected static function booted(): void
    {
        static::deleting(function (ClassroomSubject $assignment): void {
            if (Grade::query()->where('classroom_subject_id', $assignment->id)->exists()) {
                throw ValidationException::withMessages([
                    'subject' => 'Esta disciplina já possui notas lançadas na turma e não pode ser removida.',
                ]);
            }
        });
    }

    public function grades()
    {
        return $this->hasMany(Grade::class);
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function teachers()
    {
        return $this->belongsToMany(Teacher::class, 'classroom_subject_teacher', 'classroom_subject_id', 'teacher_id')
            ->withTimestamps();
    }
}
