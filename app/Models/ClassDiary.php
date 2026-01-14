<?php

namespace App\Models;

use App\Enums\ClassDiaryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassDiary extends Model
{
    use SoftDeletes;

    protected $table = 'class_diaries';

    protected $fillable = [
        'classroom_id',
        'teacher_id',
        'subject_id',
        'date',
        'content',
        'activities',
        'homework',
        'observations',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'status' => ClassDiaryStatus::class,
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
}
