<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    protected $fillable = [
        'name',
        'level_id',
    ];

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    public function teachers()
    {
        return $this->belongsToMany(Teacher::class, 'level_teacher', 'level_id', 'teacher_id');
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class);
    }
}
