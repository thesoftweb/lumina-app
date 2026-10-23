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
        'gender',
        'neurodiversy',
        'observations',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    public function getGenderLabelAttribute(): ?string
    {
        return ['M' => 'Masculino', 'F' => 'Feminino', 'O' => 'Outro'][$this->gender] ?? $this->gender;
    }

    /** Cidade e UF de nascimento; a UF é gravada pelo código IBGE do estado. */
    public function getBirthplaceAttribute(): ?string
    {
        $uf = $this->state_of_birth
            ? State::query()->where('code', $this->state_of_birth)->value('abbreviation')
            : null;

        return collect([$this->city_of_birth, $uf])->filter()->implode(' - ') ?: null;
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function latestEnrollment()
    {
        return $this->hasOne(Enrollment::class)->latestOfMany();
    }
}
