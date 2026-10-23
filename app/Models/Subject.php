<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Subject extends Model
{
    protected $fillable = [
        'name',
        'code',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Subject $subject): void {
            if ($reason = $subject->deletionBlockedReason()) {
                throw ValidationException::withMessages(['subject' => $reason]);
            }
        });
    }

    public function classrooms()
    {
        return $this->belongsToMany(Classroom::class)->using(ClassroomSubject::class);
    }

    public function deletionBlockedReason(): ?string
    {
        $hasHistory = Grade::query()
            ->whereIn('classroom_subject_id', ClassroomSubject::query()->where('subject_id', $this->id)->select('id'))
            ->exists()
            || DB::table('class_diaries')->where('subject_id', $this->id)->exists()
            || DB::table('lesson_plans')->where('subject_id', $this->id)->exists();

        if ($hasHistory) {
            return 'Esta disciplina possui notas, diários ou planos de aula e não pode ser excluída.';
        }

        return $this->classrooms()->exists()
            ? 'Esta disciplina está vinculada a turmas. Remova-a das turmas antes de excluir.'
            : null;
    }
}
