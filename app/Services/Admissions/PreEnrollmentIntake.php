<?php

namespace App\Services\Admissions;

use App\Enums\PreEnrollmentStatus;
use App\Enums\PreEnrollmentType;
use App\Models\AcademicYear;
use App\Models\AdmissionOffer;
use App\Models\Customer;
use App\Models\PreEnrollment;
use App\Models\Student;
use App\Rules\Cpf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Recebe a ficha pública de pré-matrícula. A ficha fica presa ao ano letivo do link; reenviar a mesma
 * criança no mesmo ano atualiza a ficha aberta em vez de criar um lead duplicado.
 */
class PreEnrollmentIntake
{
    /**
     * @param  array<string, mixed>  $data  campos validados da ficha (nomes das colunas de pre_enrollments)
     */
    public function submit(AcademicYear $year, array $data, ?string $ipAddress = null): PreEnrollment
    {
        if (! $year->acceptsPreEnrollments()) {
            throw ValidationException::withMessages([
                'academic_year' => 'As pré-matrículas para '.$year->description.' não estão abertas.',
            ]);
        }

        if (filled($data['admission_offer_id'] ?? null)
            && ! AdmissionOffer::query()->active()->whereKey($data['admission_offer_id'])->where('academic_year_id', $year->getKey())->exists()) {
            throw ValidationException::withMessages([
                'admission_offer_id' => 'Escolha uma das séries ofertadas para '.$year->description.'.',
            ]);
        }

        $data['student_name'] = Str::squish($data['student_name']);
        $data['guardian_name'] = Str::squish($data['guardian_name']);
        $data['guardian_document'] = Cpf::digits($data['guardian_document']);

        return DB::transaction(function () use ($year, $data, $ipAddress): PreEnrollment {
            $existing = PreEnrollment::query()->open()
                ->sameApplicant($year->getKey(), $data['guardian_document'], $data['student_name'], $data['student_birth_date'])
                ->lockForUpdate()->first();

            $attributes = [
                ...$data,
                'academic_year_id' => $year->getKey(),
                'consent_at' => now(),
                'ip_address' => $ipAddress,
                ...$this->matches($year, $data),
            ];

            if ($existing) {
                // Mantém o que a secretaria já anotou; a mensagem nova vai junto da anterior
                $attributes['message'] = collect([$existing->message, $data['message'] ?? null])->filter()->unique()->implode("\n\n") ?: null;
                $existing->fill($attributes)->save();

                return $existing;
            }

            return PreEnrollment::create([...$attributes, 'status' => PreEnrollmentStatus::New]);
        });
    }

    /**
     * Família e aluno já cadastrados: a secretaria vê o vínculo antes de converter, e a conversão
     * reaproveita os cadastros em vez de duplicar responsável ou aluno. Aluno com matrícula em ano
     * anterior faz rematrícula, mesmo que a família tenha usado a ficha pública de novatos.
     *
     * @return array{customer_id: ?int, student_id: ?int, type: PreEnrollmentType, previous_enrollment_id: ?int}
     */
    private function matches(AcademicYear $year, array $data): array
    {
        $customer = Customer::query()
            ->whereIn('document', [$data['guardian_document'], Cpf::format($data['guardian_document'])])
            ->first();

        $student = $customer ? Student::query()
            ->where('customer_id', $customer->getKey())
            ->whereDate('date_of_birth', $data['student_birth_date'])
            ->whereRaw('LOWER(name) = ?', [Str::lower($data['student_name'])])
            ->first() : null;

        $previous = $student?->previousEnrollmentBefore($year);

        return [
            'customer_id' => $customer?->getKey(),
            'student_id' => $student?->getKey(),
            'type' => $previous ? PreEnrollmentType::Reenrollment : PreEnrollmentType::NewStudent,
            'previous_enrollment_id' => $previous?->getKey(),
        ];
    }
}
