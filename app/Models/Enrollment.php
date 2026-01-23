<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'enrollment_date' => 'date',
        'status' => \App\Enums\EnrollmentStatus::class,
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function academicYear()
    {
        return $this->hasOne(AcademicYear::class);
    }

    public function grade()
    {
        return $this->hasMany(Grade::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
