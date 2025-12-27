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
        'asaas_customer_id',
    ];

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
