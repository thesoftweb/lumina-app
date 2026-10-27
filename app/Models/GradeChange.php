<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Correção de nota feita pela secretaria: quem alterou, quando, o que mudou e por quê. */
class GradeChange extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
    ];

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function classroomSubject(): BelongsTo
    {
        return $this->belongsTo(ClassroomSubject::class);
    }
}
