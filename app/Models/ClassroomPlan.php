<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassroomPlan extends Model
{
    public $table = 'classroom_plan';

    protected $fillable = ['classroom_id', 'plan_id'];

    public function classroom()
    {
        return $this->belongsToMany(Classroom::class);
    }

    public function plan()
    {
        return $this->belongsToMany(Plan::class);
    }
}
