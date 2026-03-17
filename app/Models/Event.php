<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'classroom_id',
        'company_id',
        'name',
        'description',
        'type',
        'amount',
        'due_date',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'datetime',
    ];

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function participants()
    {
        return $this->hasMany(EventParticipant::class);
    }

    public function paidParticipants()
    {
        return $this->participants()->where('status', 'paid');
    }

    public function getTotalCollected()
    {
        return $this->paidParticipants()->count() * $this->amount;
    }

    public function getPaidCount()
    {
        return $this->paidParticipants()->count();
    }

    public function isActive()
    {
        return $this->status === 'active' && now()->lessThanOrEqualTo($this->due_date);
    }
}
