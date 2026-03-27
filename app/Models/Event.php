<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
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

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relação many-to-many com as turmas que podem participar
     */
    public function classrooms()
    {
        return $this->belongsToMany(Classroom::class, 'event_classroom');
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

    /**
     * Get all enrollments for a customer across all applicable classrooms
     */
    public function getCustomerEnrollments($customerId)
    {
        $classroomIds = $this->classrooms()->pluck('classrooms.id')->toArray();

        return Enrollment::whereIn('classroom_id', $classroomIds)
            ->where('customer_id', $customerId)
            ->where('status', 'active')
            ->with('student')
            ->get();
    }
}
