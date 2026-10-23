<?php

namespace App\Models;

use App\Enums\AssessmentMode;
use App\Enums\TranscriptResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Um ano/série da vida escolar do aluno, desta escola (fechamento do ano) ou de outra (transferência). */
class TranscriptYear extends Model
{
    public const INTERNAL = 'internal';

    public const EXTERNAL = 'external';

    protected $guarded = [];

    protected $casts = [
        'year' => 'integer',
        'assessment_mode' => AssessmentMode::class,
        'attendance_percent' => 'decimal:2',
        'school_days' => 'integer',
        'total_workload' => 'integer',
        'result' => TranscriptResult::class,
        'computed_result' => TranscriptResult::class,
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(TranscriptSubject::class)->orderBy('sort_order')->orderBy('name');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isInternal(): bool
    {
        return $this->source === self::INTERNAL;
    }

    /** Resultado diferente do calculado pelo sistema (ex.: conselho de classe). */
    public function isAdjusted(): bool
    {
        return $this->computed_result !== null && $this->result !== $this->computed_result;
    }

    /** "Santa Rita/PB" */
    public function schoolPlace(): ?string
    {
        return collect([$this->school_city, $this->school_state])->filter()->implode('/') ?: null;
    }
}
