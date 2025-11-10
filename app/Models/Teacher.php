<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    protected $fillable = [
        'name',
        'date_of_birth',
        'email',
        'phone',
        'document_number',
    ];

    public function classrooms()
    {
        return $this->belongsToMany(Classroom::class, 'level_teacher', 'teacher_id', 'level_id');
    }
}
