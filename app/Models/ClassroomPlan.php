<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Relations\Pivot;

class ClassroomPlan extends Pivot
{
    public $table = 'classroom_plan';

    protected $fillable = ['classroom_id', 'plan_id'];

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
