<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bootstrap\BootProviders;

class Classroom extends Model
{
    protected $fillable = [
        'name',
        'level_id',
    ];

    protected static function booted()
    {
        static::addGlobalScope('ordered', function ($query) {
            $query->orderBy('name');
        });
    }

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    public function teachers()
    {
        return $this->belongsToMany(Teacher::class, 'level_teacher', 'level_id', 'teacher_id');
    }
}
