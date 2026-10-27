<?php

namespace App\Http\Requests;

use App\Models\AcademicYear;
use App\Models\AdmissionOffer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Confirmação da rematrícula pelo link enviado à família. Só contato, endereço, série e recado podem ser
 * alterados: os dados da criança e o CPF do responsável vêm do cadastro da escola.
 */
class ConfirmReenrollmentRequest extends FormRequest
{
    /** Campos que a família pode atualizar na ficha de rematrícula. */
    public const FIELDS = [
        'admission_offer_id', 'guardian_phone', 'guardian_email', 'postal_code', 'address', 'address_number',
        'neighborhood', 'city_name', 'message',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'guardian_phone' => preg_replace('/\D/', '', (string) $this->input('guardian_phone')),
            'postal_code' => filled($this->input('postal_code')) ? preg_replace('/\D/', '', (string) $this->input('postal_code')) : null,
        ]);
    }

    public function rules(): array
    {
        $year = AcademicYear::query()->where('year', (int) $this->route('year'))->orderBy('id')->firstOrFail();
        $hasOffers = AdmissionOffer::query()->active()->where('academic_year_id', $year->getKey())->exists();

        return [
            'admission_offer_id' => [
                Rule::requiredIf($hasOffers), 'nullable', 'integer',
                Rule::exists('admission_offers', 'id')->where('academic_year_id', $year->getKey())->where('is_active', true),
            ],
            'guardian_phone' => ['required', 'digits_between:10,11'],
            'guardian_email' => ['nullable', 'email', 'max:255'],
            'postal_code' => ['nullable', 'digits:8'],
            'address' => ['nullable', 'string', 'max:255'],
            'address_number' => ['nullable', 'string', 'max:20'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'city_name' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
        ];
    }

    public function attributes(): array
    {
        return [
            'admission_offer_id' => 'turma ou série pretendida',
            'guardian_phone' => 'telefone/WhatsApp',
            'guardian_email' => 'e-mail',
            'postal_code' => 'CEP',
            'address' => 'endereço',
            'address_number' => 'número',
            'neighborhood' => 'bairro',
            'city_name' => 'cidade',
            'message' => 'mensagem',
            'consent' => 'autorização de uso dos dados',
        ];
    }

    public function messages(): array
    {
        return [
            'admission_offer_id.required' => 'Escolha a turma ou série pretendida.',
            'admission_offer_id.exists' => 'Escolha uma das turmas ou séries ofertadas para este ano letivo.',
            'guardian_phone.required' => 'Informe o telefone com DDD.',
            'guardian_phone.digits_between' => 'Informe o telefone com DDD, ex.: (83) 99999-9999.',
            'postal_code.digits' => 'Informe um CEP com 8 dígitos.',
            'consent.accepted' => 'Para confirmar, autorize o uso dos dados para contato da escola.',
            'email' => 'Informe um e-mail válido.',
            'max' => 'O campo :attribute está muito longo.',
        ];
    }

    /** @return array<string, mixed> */
    public function reenrollmentData(): array
    {
        return collect($this->validated())->only(self::FIELDS)->all();
    }
}
