<?php

namespace App\Models;

use App\Documents\PickupCard;
use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class Student extends Model
{
    /** Foto de criança: disco privado, nunca exposta em URL pública. */
    public const PHOTO_DISK = 'local';

    public const PHOTO_DIRECTORY = 'student-photos';

    protected $fillable = [
        'name',
        'photo_path',
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

    protected static function booted(): void
    {
        // Ao trocar ou remover a foto, o arquivo anterior sai do disco (as carteirinhas guardam cópia própria)
        static::updated(function (Student $student): void {
            $previous = $student->getOriginal('photo_path');

            if ($student->wasChanged('photo_path') && $previous) {
                Storage::disk(self::PHOTO_DISK)->delete($previous);
            }
        });

        // As autorizações saem do banco em cascata, sem eventos: as carteirinhas são canceladas antes
        static::deleting(function (Student $student): void {
            if ($student->transcriptYears()->exists()) {
                throw ValidationException::withMessages([
                    'student' => 'Este aluno tem histórico escolar registrado e não pode ser excluído.',
                ]);
            }

            $student->pickupAuthorizations->each(fn (StudentPickupAuthorization $authorization) => PickupCard::cancelFor(
                $authorization,
                'Cadastro do aluno excluído em '.now()->format('d/m/Y').'.',
            ));
        });

        static::deleted(function (Student $student): void {
            if ($student->photo_path) {
                Storage::disk(self::PHOTO_DISK)->delete($student->photo_path);
            }
        });
    }

    /** Anos da vida escolar para o histórico (desta e de outras escolas). */
    public function transcriptYears()
    {
        return $this->hasMany(TranscriptYear::class)->orderBy('year')->orderBy('id');
    }

    public function pickupAuthorizations()
    {
        return $this->hasMany(StudentPickupAuthorization::class);
    }

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

    /**
     * Última matrícula não cancelada de um ano anterior ao informado. Sem ela o aluno é novato naquele ano.
     * Matrículas sem ano letivo (legado) contam como anteriores.
     */
    public function previousEnrollmentBefore(AcademicYear $academicYear): ?Enrollment
    {
        return $this->enrollments()
            ->where('status', '!=', EnrollmentStatus::Canceled->value)
            ->where(fn ($query) => $query->whereNull('academic_year_id')
                ->orWhereHas('academicYear', fn ($query) => $query->where('year', '<', $academicYear->year)))
            ->orderByDesc('enrollment_date')->orderByDesc('id')
            ->first();
    }

    public function isNewStudentIn(AcademicYear $academicYear): bool
    {
        return $this->previousEnrollmentBefore($academicYear) === null;
    }

    public function latestEnrollment()
    {
        return $this->hasOne(Enrollment::class)->latestOfMany();
    }
}
