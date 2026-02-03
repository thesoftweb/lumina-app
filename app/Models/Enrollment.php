<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'enrollment_date' => 'date',
        'status' => \App\Enums\EnrollmentStatus::class,
        'doc_historical_delivered' => 'boolean',
        'doc_photo_3x4_delivered' => 'boolean',
        'doc_declaration_delivered' => 'boolean',
        'doc_residence_proof_delivered' => 'boolean',
        'doc_student_document_delivered' => 'boolean',
        'doc_responsible_document_delivered' => 'boolean',
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

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
