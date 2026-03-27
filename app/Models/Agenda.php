<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agenda extends Model
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

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Retorna agendas globais ou da turma específica
     */
    public function scopeForClassroom($query, ?int $classroomId)
    {
        return $query->where('global', true)
            ->orWhere(function ($q) use ($classroomId) {
                $q->where('classroom_id', $classroomId)
                    ->where('global', false);
            });
    }

    /**
     * Retorna agendas futuras
     */
    public function scopeUpcoming($query)
    {
        return $query->where('date', '>=', now());
    }
}
