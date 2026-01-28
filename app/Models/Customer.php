<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'document',
        'address',
        'address_number',
        'address_complement',
        'neighborhood',
        'state',
        'city_id',
        'postal_code',
        'asaas_customer_id',
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
