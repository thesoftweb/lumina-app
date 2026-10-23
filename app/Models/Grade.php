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
        'grade_1',
        'grade_2',
        'recovery_grade',
        'concept_scale_id',
        'enrollment_id',
        'comments',
    ];

    protected $casts = [
        'grade' => 'decimal:2',
        'grade_1' => 'decimal:2',
        'grade_2' => 'decimal:2',
        'recovery_grade' => 'decimal:2',
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

    public function conceptScale()
    {
        return $this->belongsTo(ConceptScale::class);
    }

    /** Correções feitas pela secretaria. */
    public function changes()
    {
        return $this->hasMany(GradeChange::class);
    }

    public function classroomSubject()
    {
        return $this->belongsTo(ClassroomSubject::class);
    }
}
