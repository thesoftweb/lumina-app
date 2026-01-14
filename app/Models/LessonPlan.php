<?php

namespace App\Models;

use App\Enums\LessonPlanStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LessonPlan extends Model
{
    use SoftDeletes;

    protected $table = 'lesson_plans';

    protected $fillable = [
        'classroom_id',
        'teacher_id',
        'subject_id',
        'term_id',
        'title',
        'description',
        'objectives',
        'methodology',
        'resources',
        'duration_minutes',
        'scheduled_date',
        'status',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'status' => LessonPlanStatus::class,
    ];

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }
}
