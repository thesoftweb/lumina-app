<?php

namespace App\Models;

use App\Enums\AssessmentMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Classroom extends Model
{
    protected $fillable = [
        'name',
        'level_id',
        'assessment_mode',
    ];

    protected $casts = [
        'assessment_mode' => AssessmentMode::class,
    ];

    protected static function booted()
    {
        static::addGlobalScope('ordered', function ($query) {
            $query->orderBy('name');
        });

        static::deleting(function (Classroom $classroom): void {
            $hasHistory = $classroom->enrollments()->exists()
                || \Illuminate\Support\Facades\DB::table('attendances')->where('classroom_id', $classroom->id)->exists()
                || \Illuminate\Support\Facades\DB::table('class_diaries')->where('classroom_id', $classroom->id)->exists()
                || \Illuminate\Support\Facades\DB::table('lesson_plans')->where('classroom_id', $classroom->id)->exists();

            if ($hasHistory) {
                throw ValidationException::withMessages([
                    'classroom' => 'Esta turma possui matrículas, frequência ou diário e não pode ser excluída.',
                ]);
            }
        });

        static::updating(function (Classroom $classroom): void {
            if ($classroom->isDirty('assessment_mode') && $classroom->enrollments()->whereHas('grade')->exists()) {
                throw ValidationException::withMessages([
                    'assessment_mode' => 'O modo de avaliação não pode ser alterado após o lançamento de resultados.',
                ]);
            }
        });
    }

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    public function teachers()
    {
        return $this->belongsToMany(Teacher::class, 'classroom_teacher', 'classroom_id', 'teacher_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class)->using(ClassroomSubject::class);
    }

    public function classroomSubjects()
    {
        return $this->hasMany(ClassroomSubject::class);
    }

    public function plans()
    {
        return $this->belongsToMany(Plan::class)->using(ClassroomPlan::class);
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function schedules()
    {
        return $this->belongsToMany(Schedule::class, 'classroom_schedule')->withTimestamps();
    }
}
