<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ClassroomSubject extends Pivot
{
    public $table = 'classroom_subject';

    public $fillable = [
        'classroom_id',
        'subject_id',
    ];

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
