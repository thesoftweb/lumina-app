<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{

    public $fillable = [
        'classroom_subject_id',
        'term_id',
        'teacher_id',
        'grade',
        'enrollment_id',
        'comments'
    ];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function classroomSubject()
    {
        return $this->belongsTo(ClassroomSubject::class);
    }
}
