<?php

namespace App\Models;

use App\Enums\LeadSource;
use App\Enums\PreEnrollmentStatus;
use App\Enums\PreEnrollmentType;
use App\Rules\Cpf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Ficha de pré-matrícula recebida pelo link público (lead). Pertence sempre a um ano letivo: a matrícula
 * criada a partir dela é desse ano, e a mesma família pode ter fichas de anos diferentes sem se misturarem.
 */
class PreEnrollment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => PreEnrollmentStatus::class,
        'type' => PreEnrollmentType::class,
        'source' => LeadSource::class,
        'responded_at' => 'datetime',
        'student_birth_date' => 'date',
        'student_has_special_needs' => 'boolean',
        'consent_at' => 'datetime',
        'last_contact_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (PreEnrollment $preEnrollment): void {
            $preEnrollment->code ??= static::newCode($preEnrollment->academicYear?->year);
        });
    }

    /** Protocolo curto e sem caracteres ambíguos, ex.: 2027-K7QH3M. */
    public static function newCode(?int $year = null): string
    {
        do {
            $code = ($year ?? now()->year).'-'.Str::upper(Str::of(Str::random(12))->replaceMatches('/[^A-HJ-NP-Z2-9]/i', '')->substr(0, 6));
        } while (strlen($code) < 11 || static::query()->where('code', $code)->exists());

        return $code;
    }

    /** Link da rematrícula enviado à família; o token é criado na primeira vez que o link é pedido. */
    public function reenrollmentUrl(): string
    {
        if (! $this->access_token) {
            $this->forceFill(['access_token' => Str::random(40)])->save();
        }

        return route('pre-enrollment.reenrollment', [$this->academicYear->year, $this->access_token]);
    }

    /** O link de rematrícula vale enquanto a ficha está aberta e o ano letivo não terminou. */
    public function acceptsReenrollmentResponse(): bool
    {
        return $this->type === PreEnrollmentType::Reenrollment
            && $this->status->isOpen()
            && (! $this->academicYear->end_at || $this->academicYear->end_at->gte(today()));
    }

    public function allowsDiscount(): bool
    {
        return ($this->type ?? PreEnrollmentType::NewStudent)->allowsDiscount();
    }

    public function setGuardianDocumentAttribute(?string $value): void
    {
        $this->attributes['guardian_document'] = $value === null ? null : Cpf::digits($value);
    }

    public function setGuardianPhoneAttribute(?string $value): void
    {
        $this->attributes['guardian_phone'] = $value === null ? null : preg_replace('/\D/', '', $value);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', array_map(fn (PreEnrollmentStatus $status) => $status->value, PreEnrollmentStatus::open()));
    }

    public function scopeForYear($query, AcademicYear|int $academicYear)
    {
        return $query->where('academic_year_id', $academicYear instanceof AcademicYear ? $academicYear->getKey() : $academicYear);
    }

    /** Mesma criança, mesmo responsável e mesmo ano: usado para não duplicar a ficha reenviada. */
    public function scopeSameApplicant($query, int $academicYearId, string $guardianDocument, string $studentName, $birthDate)
    {
        return $query->where('academic_year_id', $academicYearId)
            ->where('guardian_document', Cpf::digits($guardianDocument))
            ->whereDate('student_birth_date', $birthDate)
            ->whereRaw('LOWER(student_name) = ?', [Str::lower(Str::squish($studentName))]);
    }

    public function guardianDocumentFormatted(): string
    {
        return Cpf::format((string) $this->guardian_document);
    }

    /** Link do WhatsApp para o telefone do responsável (DDI 55 quando vier só com DDD). */
    public function whatsappUrl(?string $message = null): ?string
    {
        $phone = (string) $this->guardian_phone;

        if (strlen($phone) < 10) {
            return null;
        }

        $phone = strlen($phone) <= 11 ? '55'.$phone : $phone;

        return 'https://wa.me/'.$phone.($message ? '?text='.rawurlencode($message) : '');
    }

    /** Idade da criança no início do ano letivo da ficha (referência para a série). */
    public function studentAgeAtYearStart(): ?int
    {
        if (! $this->student_birth_date || ! $this->academicYear) {
            return null;
        }

        $reference = $this->academicYear->start_at ?? now()->setDate($this->academicYear->year, 3, 31);

        return (int) $this->student_birth_date->diffInYears($reference);
    }

    public function isConverted(): bool
    {
        return $this->status === PreEnrollmentStatus::Converted;
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function offer()
    {
        return $this->belongsTo(AdmissionOffer::class, 'admission_offer_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function previousEnrollment()
    {
        return $this->belongsTo(Enrollment::class, 'previous_enrollment_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function converter()
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    public function contacts()
    {
        return $this->hasMany(PreEnrollmentContact::class)->latest()->latest('id');
    }
}
