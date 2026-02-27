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
        'user_id',
        'account_type',
        'bank_name',
        'bank_code',
        'agency_number',
        'account_number',
        'account_holder_name',
        'pix_key_type',
        'pix_key',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function classrooms()
    {
        return $this->belongsToMany(Classroom::class, 'level_teacher', 'teacher_id', 'level_id');
    }
}
