<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Level extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Level $level): void {
            if ($reason = $level->deletionBlockedReason()) {
                throw ValidationException::withMessages(['level' => $reason]);
            }
        });
    }

    public function classrooms()
    {
        return $this->hasMany(Classroom::class);
    }

    public function deletionBlockedReason(): ?string
    {
        return $this->classrooms()->exists()
            ? 'Este nível possui turmas vinculadas e não pode ser excluído.'
            : null;
    }
}
