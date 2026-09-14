<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $fillable = [
        'title',
        'description',
        'date',
        'classroom_id',
        'global',
        'company_id',
    ];

    protected $casts = [
        'date' => 'datetime',
        'global' => 'boolean',
    ];

    protected static function booted()
    {
        static::addGlobalScope('company', function ($query) {
            if (auth()->check() && auth()->user()->company_id) {
                $query->where('company_id', auth()->user()->company_id);
            }
        });

        static::addGlobalScope('ordered', function ($query) {
            $query->orderBy('date', 'asc');
        });
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function classrooms()
    {
        return $this->belongsToMany(Classroom::class, 'classroom_schedule')->withTimestamps();
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Retorna agendas globais ou da turma especifica.
     */
    public function scopeForClassroom($query, ?int $classroomId)
    {
        return $query->forClassrooms($classroomId === null ? [] : [$classroomId]);
    }

    public function scopeForClassrooms($query, iterable $classroomIds)
    {
        $classroomIds = collect($classroomIds)->filter()->unique()->values();

        return $query->where(function ($query) use ($classroomIds) {
            $query->where('global', true)
                ->when($classroomIds->isNotEmpty(), function ($query) use ($classroomIds) {
                    $query->orWhere(function ($query) use ($classroomIds) {
                        $query->where('global', false)
                            ->where(function ($query) use ($classroomIds) {
                                $query->whereHas('classrooms', fn ($query) => $query->whereIn('classrooms.id', $classroomIds))
                                    ->orWhereIn('classroom_id', $classroomIds);
                            });
                    });
                });
        });
    }

    /**
     * Retorna agendas futuras.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('date', '>=', now());
    }
}
