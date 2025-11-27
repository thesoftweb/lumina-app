<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $fillable = [
        'name',
        'date_of_birth',
        'state_of_birth',
        'city_of_birth',
        'affiliation_1',
        'affiliation_2',
        'phone_primary',
        'phone_secondary',
        'reg_number',
        'doc_number',
        'customer_id',
        'degree_of_kinship',
        'gender'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
