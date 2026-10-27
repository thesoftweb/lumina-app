<?php

namespace App\Services\Admissions;

use App\Enums\EnrollmentStatus;
use App\Enums\PreEnrollmentStatus;
use App\Enums\PreEnrollmentType;
use App\Models\Classroom;
use App\Models\ClassroomPlan;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Plan;
use App\Models\PreEnrollment;
use App\Models\Student;
use App\Models\User;
use App\Rules\Cpf;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Transforma a pré-matrícula em matrícula do ano letivo da ficha: reaproveita ou cria o responsável e o
 * aluno e cria a matrícula como reservada. Turma e plano precisam ser do mesmo ano; turmas e planos sem
 * ano (legado) só são aceitos para o ano letivo padrão, para que uma matrícula nova nunca caia em 2026.
 * Faturas não são geradas aqui: a secretaria gera taxa e mensalidades pela matrícula, como hoje.
 */
class PreEnrollmentConverter
{
    /**
     * @param  array{classroom_id?: ?int, plan_id?: ?int, day_of_payment: int, enrollment_date?: mixed, status?: EnrollmentStatus|string|null, apply_plan_discount?: bool, use_custom_discount?: bool, discount_type?: ?string, discount_value?: mixed, discount_reason?: ?string}  $data
     */
    public function convert(PreEnrollment $preEnrollment, array $data, ?User $user = null): Enrollment
    {
        if ($preEnrollment->isConverted()) {
            throw ValidationException::withMessages(['pre_enrollment' => 'Esta pré-matrícula já foi convertida em matrícula.']);
        }

        $year = $preEnrollment->academicYear;
        $classroom = Classroom::query()->find($data['classroom_id'] ?? $preEnrollment->offer?->classroom_id);
        $planId = $data['plan_id'] ?? $preEnrollment->offer?->plan_id;

        if (! $classroom || ! $this->belongsToYear($classroom->academic_year_id, $preEnrollment)) {
            throw ValidationException::withMessages(['classroom_id' => 'Escolha uma turma de '.$year->description.'.']);
        }

        if ($planId && ! $this->belongsToYear(Plan::query()->whereKey($planId)->value('academic_year_id'), $preEnrollment, Plan::query()->whereKey($planId)->exists())) {
            throw ValidationException::withMessages(['plan_id' => 'Escolha um plano de '.$year->description.'.']);
        }

        if ($planId && ! ClassroomPlan::query()->where('classroom_id', $classroom->getKey())->where('plan_id', $planId)->exists()) {
            throw ValidationException::withMessages(['plan_id' => 'Este plano não está vinculado à turma escolhida.']);
        }

        $discount = $this->negotiatedDiscount($data, $planId ? Plan::query()->find($planId) : null);

        return DB::transaction(function () use ($preEnrollment, $data, $classroom, $planId, $discount, $user): Enrollment {
            $customer = $this->customer($preEnrollment);
            $student = $this->student($preEnrollment, $customer);

            // Novato ou veterano pelo histórico real do aluno, não pela ficha: a secretaria pode ter vinculado um cadastro
            $previous = $student->previousEnrollmentBefore($preEnrollment->academicYear);

            if ($discount && ! $previous) {
                throw ValidationException::withMessages(['use_custom_discount' => Enrollment::NEW_STUDENT_DISCOUNT_MESSAGE]);
            }

            $enrollment = Enrollment::create([
                ...($discount ?? []),
                'previous_enrollment_id' => $previous?->getKey(),
                'student_id' => $student->getKey(),
                'customer_id' => $customer->getKey(),
                'academic_year_id' => $preEnrollment->academic_year_id,
                'classroom_id' => $classroom->getKey(),
                'plan_id' => $planId,
                'day_of_payment' => $data['day_of_payment'],
                'enrollment_date' => $data['enrollment_date'] ?? today(),
                'status' => $data['status'] ?? EnrollmentStatus::Reserved,
                'new_student' => $previous === null,
            ]);

            $preEnrollment->forceFill([
                'status' => PreEnrollmentStatus::Converted,
                'type' => $previous ? PreEnrollmentType::Reenrollment : PreEnrollmentType::NewStudent,
                'previous_enrollment_id' => $previous?->getKey(),
                'customer_id' => $customer->getKey(),
                'student_id' => $student->getKey(),
                'enrollment_id' => $enrollment->getKey(),
                'converted_at' => now(),
                'converted_by' => ($user ?? auth()->user())?->getKey(),
            ])->save();

            return $enrollment;
        });
    }

    /**
     * Desconto combinado com a família na rematrícula: o desconto configurado no plano ou um desconto próprio
     * (valor ou percentual, com motivo). A mensalidade continua sendo a do plano; o desconto fica registrado
     * na matrícula e é aplicado pelas regras de sempre na geração das faturas.
     *
     * @return array<string, mixed>|null
     */
    private function negotiatedDiscount(array $data, ?Plan $plan): ?array
    {
        if (! empty($data['use_custom_discount']) && ! empty($data['apply_plan_discount'])) {
            throw ValidationException::withMessages(['apply_plan_discount' => Enrollment::DISCOUNT_CHOICE_MESSAGE]);
        }

        if (! empty($data['apply_plan_discount'])) {
            if (! $plan?->has_discount) {
                throw ValidationException::withMessages(['apply_plan_discount' => 'O plano escolhido não tem desconto configurado.']);
            }

            return [
                'apply_plan_discount' => true,
                'discount_reason' => filled($data['discount_reason'] ?? null) ? trim($data['discount_reason']) : null,
            ];
        }

        if (empty($data['use_custom_discount'])) {
            return null;
        }

        $type = $data['discount_type'] ?? null;
        $value = (float) ($data['discount_value'] ?? 0);

        if (! in_array($type, ['percentage', 'fixed'], true) || $value <= 0 || ($type === 'percentage' && $value > 100)) {
            throw ValidationException::withMessages(['discount_value' => 'Informe o tipo e um valor de desconto válido.']);
        }

        if (blank($data['discount_reason'] ?? null)) {
            throw ValidationException::withMessages(['discount_reason' => 'Registre o motivo do desconto negociado.']);
        }

        return [
            'use_custom_discount' => true,
            'discount_type' => $type,
            'discount_value' => $value,
            'discount_reason' => trim($data['discount_reason']),
        ];
    }

    /** Ano da turma ou do plano: igual ao da ficha, ou sem ano quando a ficha é do ano letivo padrão. */
    private function belongsToYear(?int $academicYearId, PreEnrollment $preEnrollment, bool $exists = true): bool
    {
        if (! $exists) {
            return false;
        }

        return $academicYearId === null
            ? (bool) $preEnrollment->academicYear->is_default
            : $academicYearId === $preEnrollment->academic_year_id;
    }

    /** Responsável pelo CPF: o cadastro existente não é sobrescrito, só tem os campos vazios completados. */
    private function customer(PreEnrollment $preEnrollment): Customer
    {
        $document = (string) $preEnrollment->guardian_document;

        $customer = ($preEnrollment->customer_id ? Customer::query()->find($preEnrollment->customer_id) : null)
            ?? Customer::query()->whereIn('document', [$document, Cpf::format($document)])->first()
            ?? new Customer(['document' => Cpf::format($document)]);

        // customers.email é único: o e-mail da ficha só entra se não for de outro cliente
        $emailTaken = filled($preEnrollment->guardian_email) && Customer::query()
            ->where('email', $preEnrollment->guardian_email)
            ->when($customer->exists, fn ($query) => $query->whereKeyNot($customer->getKey()))
            ->exists();

        foreach ([
            'name' => $preEnrollment->guardian_name,
            'email' => $emailTaken ? null : $preEnrollment->guardian_email,
            'phone' => $preEnrollment->guardian_phone,
            'postal_code' => $preEnrollment->postal_code,
            'address' => $preEnrollment->address,
            'address_number' => $preEnrollment->address_number,
            'neighborhood' => $preEnrollment->neighborhood,
        ] as $field => $value) {
            if (blank($customer->{$field}) && filled($value)) {
                $customer->{$field} = $value;
            }
        }

        $customer->save();

        return $customer;
    }

    private function student(PreEnrollment $preEnrollment, Customer $customer): Student
    {
        $student = $preEnrollment->student_id ? Student::query()->find($preEnrollment->student_id) : null;

        $student ??= Student::query()
            ->where('customer_id', $customer->getKey())
            ->whereDate('date_of_birth', $preEnrollment->student_birth_date)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($preEnrollment->student_name)])
            ->first();

        return $student ?? Student::create([
            'name' => $preEnrollment->student_name,
            'date_of_birth' => $preEnrollment->student_birth_date,
            // students.gender é NOT NULL com padrão: sem gênero na ficha, a coluna fica com o padrão do banco
            ...array_filter(['gender' => $preEnrollment->student_gender]),
            'customer_id' => $customer->getKey(),
            'degree_of_kinship' => $preEnrollment->guardian_kinship,
            'phone_primary' => $preEnrollment->guardian_phone,
            // A neurodiversidade do cadastro é uma lista de perfis; o relato livre da família vai para as observações
            'observations' => $preEnrollment->student_has_special_needs
                ? 'Necessidade específica informada na pré-matrícula: '.($preEnrollment->student_special_needs_notes ?: 'sem detalhes.')
                : null,
        ]);
    }
}
