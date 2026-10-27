<?php

namespace App\Http\Requests;

use App\Enums\DegreeOfKinship;
use App\Enums\LeadSource;
use App\Models\AcademicYear;
use App\Models\AdmissionOffer;
use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Ficha pública de pré-matrícula. O ano letivo vem da URL (/pre-matricula/{year}). */
class StorePreEnrollmentRequest extends FormRequest
{
    /** Campos gravados na ficha; o aceite LGPD e o honeypot não viram coluna. */
    public const FIELDS = [
        'admission_offer_id', 'student_name', 'student_birth_date', 'student_gender', 'student_previous_school',
        'student_has_special_needs', 'student_special_needs_notes', 'guardian_name', 'guardian_document',
        'guardian_kinship', 'guardian_email', 'guardian_phone', 'postal_code', 'address', 'address_number',
        'neighborhood', 'city_name', 'source', 'message', 'utm_source', 'utm_medium', 'utm_campaign',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function academicYear(): AcademicYear
    {
        return AcademicYear::query()->where('year', (int) $this->route('year'))->orderBy('id')->firstOrFail();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'guardian_phone' => preg_replace('/\D/', '', (string) $this->input('guardian_phone')),
            'postal_code' => filled($this->input('postal_code')) ? preg_replace('/\D/', '', (string) $this->input('postal_code')) : null,
            'student_has_special_needs' => $this->boolean('student_has_special_needs'),
            ...collect(['utm_source', 'utm_medium', 'utm_campaign'])
                ->mapWithKeys(fn (string $key): array => [$key => filled($this->input($key)) ? Str::limit((string) $this->input($key), 100, '') : null])
                ->all(),
        ]);
    }

    public function rules(): array
    {
        $year = $this->academicYear();
        $hasOffers = AdmissionOffer::query()->active()->where('academic_year_id', $year->getKey())->exists();

        return [
            'admission_offer_id' => [
                Rule::requiredIf($hasOffers), 'nullable', 'integer',
                Rule::exists('admission_offers', 'id')->where('academic_year_id', $year->getKey())->where('is_active', true),
            ],
            'student_name' => ['required', 'string', 'min:5', 'max:255'],
            'student_birth_date' => ['required', 'date', 'before:today', 'after:'.today()->subYears(20)->toDateString()],
            'student_gender' => ['nullable', Rule::in(['M', 'F', 'O'])],
            'student_previous_school' => ['nullable', 'string', 'max:255'],
            'student_has_special_needs' => ['boolean'],
            'student_special_needs_notes' => ['nullable', 'string', 'max:1000'],
            'guardian_name' => ['required', 'string', 'min:5', 'max:255'],
            'guardian_document' => ['required', 'string', 'max:14', new Cpf],
            'guardian_kinship' => ['nullable', Rule::enum(DegreeOfKinship::class)],
            'guardian_email' => ['nullable', 'email', 'max:255'],
            'guardian_phone' => ['required', 'digits_between:10,11'],
            'postal_code' => ['nullable', 'digits:8'],
            'address' => ['nullable', 'string', 'max:255'],
            'address_number' => ['nullable', 'string', 'max:20'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'city_name' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', Rule::enum(LeadSource::class)],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'admission_offer_id' => 'turma ou série pretendida',
            'student_name' => 'nome da criança',
            'student_birth_date' => 'data de nascimento',
            'student_gender' => 'sexo',
            'student_previous_school' => 'escola atual',
            'student_special_needs_notes' => 'descrição da necessidade',
            'guardian_name' => 'nome do responsável',
            'guardian_document' => 'CPF do responsável',
            'guardian_kinship' => 'parentesco',
            'guardian_email' => 'e-mail',
            'guardian_phone' => 'telefone/WhatsApp',
            'postal_code' => 'CEP',
            'address' => 'endereço',
            'address_number' => 'número',
            'neighborhood' => 'bairro',
            'city_name' => 'cidade',
            'source' => 'como conheceu a escola',
            'message' => 'mensagem',
            'consent' => 'autorização de uso dos dados',
        ];
    }

    public function messages(): array
    {
        return [
            'admission_offer_id.required' => 'Escolha a turma ou série pretendida.',
            'admission_offer_id.exists' => 'Escolha uma das turmas ou séries ofertadas para este ano letivo.',
            'student_birth_date.before' => 'Informe uma data de nascimento válida.',
            'student_birth_date.after' => 'Informe uma data de nascimento válida.',
            'guardian_phone.digits_between' => 'Informe o telefone com DDD, ex.: (83) 99999-9999.',
            'postal_code.digits' => 'Informe um CEP com 8 dígitos.',
            'consent.accepted' => 'Para enviar a ficha, autorize o uso dos dados para contato da escola.',
            'required' => 'Preencha o campo :attribute.',
            'email' => 'Informe um e-mail válido.',
            'min' => 'O campo :attribute está muito curto.',
            'max' => 'O campo :attribute está muito longo.',
            'date' => 'Informe uma data válida.',
            'in' => 'Escolha uma opção válida para :attribute.',
            'enum' => 'Escolha uma opção válida para :attribute.',
        ];
    }

    /** @return array<string, mixed> só os campos da ficha, prontos para o PreEnrollmentIntake */
    public function preEnrollmentData(): array
    {
        return collect($this->validated())->only(self::FIELDS)->all();
    }
}
