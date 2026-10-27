<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Disciplina de um ano do histórico: nota final ou conceito e carga horária. */
class TranscriptSubject extends Model
{
    protected $guarded = [];

    protected $casts = [
        'final_grade' => 'decimal:2',
        'workload_hours' => 'integer',
        'sort_order' => 'integer',
    ];

    public function transcriptYear(): BelongsTo
    {
        return $this->belongsTo(TranscriptYear::class);
    }

    /** "8,50" ou o conceito ("B"); "—" sem resultado. */
    public function resultLabel(): string
    {
        return $this->final_grade !== null
            ? number_format((float) $this->final_grade, 2, ',', '.')
            : ($this->concept ?: '—');
    }
}
