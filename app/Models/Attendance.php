<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'enrollment_id',
        'classroom_id',
        'teacher_id',
        'date',
        'present',
        'justified',
        'justification',
    ];

    protected $casts = [
        'date' => 'date',
        'present' => 'boolean',
        'justified' => 'boolean',
    ];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }
}
