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
        'asaas_sync_status',
        'asaas_sync_error',
        'asaas_synced_at',
    ];

    protected $casts = [
        'asaas_synced_at' => 'datetime',
    ];

    public function getFullAddressAttribute(): ?string
    {
        $city = collect([$this->city?->name, $this->city?->state?->abbreviation])->filter()->implode('/');

        return collect([
            collect([$this->address, $this->address_number])->filter()->implode(', '),
            $this->address_complement,
            $this->neighborhood,
            $city,
            $this->postal_code ? "CEP {$this->postal_code}" : null,
        ])->filter()->implode(' - ') ?: null;
    }

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
