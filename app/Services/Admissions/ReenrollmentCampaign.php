<?php

namespace App\Services\Admissions;

use App\Enums\EnrollmentStatus;
use App\Enums\PreEnrollmentStatus;
use App\Enums\PreEnrollmentType;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\PreEnrollment;
use App\Rules\Cpf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Abre a rematrícula: cria uma ficha de rematrícula no ano novo para cada aluno matriculado no ano de
 * origem que ainda não tem matrícula nem ficha no ano novo. As fichas já vêm com os dados do cadastro
 * e com o link próprio que a secretaria envia à família. Rodar de novo só cria as que faltam.
 */
class ReenrollmentCampaign
{
    /** Matrículas do ano de origem que ainda precisam de ficha de rematrícula no ano novo. */
    public function pending(AcademicYear $source, AcademicYear $target): Builder
    {
        return $this->candidates($source, $target)
            ->whereHas('student', fn ($query) => $query->whereNotNull('date_of_birth')
                ->whereHas('customer', fn ($query) => $query->whereNotNull('document')->where('document', '!=', '')));
    }

    /**
     * Alunos que precisam de rematrícula mas não têm data de nascimento ou CPF do responsável no cadastro:
     * a secretaria completa o cadastro e roda a geração de novo.
     */
    public function incomplete(AcademicYear $source, AcademicYear $target): Builder
    {
        return $this->candidates($source, $target)->whereNotIn('id', $this->pending($source, $target)->select('enrollments.id'));
    }

    private function candidates(AcademicYear $source, AcademicYear $target): Builder
    {
        return Enrollment::query()
            ->where('academic_year_id', $source->getKey())
            ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Started->value, EnrollmentStatus::Completed->value])
            ->whereHas('student')
            ->whereDoesntHave('student.enrollments', fn ($query) => $query
                ->where('academic_year_id', $target->getKey())
                ->where('status', '!=', EnrollmentStatus::Canceled->value))
            ->whereNotIn('student_id', PreEnrollment::query()
                ->where('academic_year_id', $target->getKey())
                ->whereNotNull('student_id')
                ->select('student_id'))
            ->with(['student.customer']);
    }

    /** @return Collection<int, PreEnrollment> fichas criadas */
    public function generate(AcademicYear $source, AcademicYear $target): Collection
    {
        if ($target->year <= $source->year) {
            throw new \InvalidArgumentException('A rematrícula precisa ir para um ano letivo posterior ao de origem.');
        }

        return DB::transaction(fn (): Collection => $this->pending($source, $target)->get()
            // Um aluno com mais de uma matrícula no ano de origem (ex.: remanejado) recebe uma ficha só
            ->unique('student_id')
            ->map(fn (Enrollment $enrollment): PreEnrollment => $this->createFor($enrollment, $target))
            ->values());
    }

    private function createFor(Enrollment $enrollment, AcademicYear $target): PreEnrollment
    {
        $student = $enrollment->student;
        $customer = $student->customer;

        return PreEnrollment::create([
            'academic_year_id' => $target->getKey(),
            'type' => PreEnrollmentType::Reenrollment,
            'status' => PreEnrollmentStatus::New,
            'previous_enrollment_id' => $enrollment->getKey(),
            'student_id' => $student->getKey(),
            'customer_id' => $customer->getKey(),
            'student_name' => $student->name,
            'student_birth_date' => $student->date_of_birth,
            'student_gender' => $student->gender,
            'guardian_name' => $customer->name,
            'guardian_document' => Cpf::digits($customer->document),
            'guardian_kinship' => $student->degree_of_kinship,
            'guardian_email' => $customer->email,
            'guardian_phone' => (string) ($customer->phone ?: $student->phone_primary),
            'postal_code' => $customer->postal_code,
            'address' => $customer->address,
            'address_number' => $customer->address_number,
            'neighborhood' => $customer->neighborhood,
            'city_name' => $customer->city?->name,
            'access_token' => Str::random(40),
        ]);
    }
}
