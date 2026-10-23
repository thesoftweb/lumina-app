<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Teacher extends Model
{
    protected $fillable = [
        'name',
        'date_of_birth',
        'email',
        'phone',
        'document_number',
        'user_id',
        'account_type',
        'bank_name',
        'bank_code',
        'agency_number',
        'account_number',
        'account_holder_name',
        'pix_key_type',
        'pix_key',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Teacher $teacher): void {
            $hasHistory = collect(['grades', 'attendances', 'class_diaries', 'lesson_plans'])
                ->contains(fn (string $table): bool => DB::table($table)->where('teacher_id', $teacher->id)->exists());

            if ($hasHistory) {
                throw ValidationException::withMessages([
                    'teacher' => 'Este professor possui notas, frequência, diários ou planos registrados e não pode ser excluído.',
                ]);
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function classrooms()
    {
        return $this->belongsToMany(Classroom::class, 'classroom_teacher', 'teacher_id', 'classroom_id');
    }

    public function classroomSubjects()
    {
        return $this->belongsToMany(ClassroomSubject::class, 'classroom_subject_teacher', 'teacher_id', 'classroom_subject_id')
            ->withTimestamps();
    }
}
