<?php

namespace App\Http\Controllers;

use App\Enums\PreEnrollmentType;
use App\Http\Requests\ConfirmReenrollmentRequest;
use App\Models\AcademicYear;
use App\Models\Company;
use App\Models\PreEnrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Link de rematrícula enviado pela secretaria (/pre-matricula/2027/rematricula/{token}). O token identifica
 * a ficha; a página mostra só a criança e o contato do responsável, nunca o CPF completo nem dados financeiros.
 */
class ReenrollmentController extends Controller
{
    public function show(int $year, string $token): View
    {
        $preEnrollment = $this->findPreEnrollment($year, $token);

        if (! $preEnrollment->acceptsReenrollmentResponse()) {
            return $this->inactive($preEnrollment->academicYear);
        }

        return view('pre-enrollment.reenrollment', [
            'preEnrollment' => $preEnrollment,
            'academicYear' => $preEnrollment->academicYear,
            'offers' => PreEnrollmentController::offers($preEnrollment->academicYear),
            'maskedDocument' => self::maskDocument((string) $preEnrollment->guardian_document),
            'company' => $this->company(),
        ]);
    }

    public function confirm(int $year, string $token): View|RedirectResponse
    {
        $preEnrollment = $this->findPreEnrollment($year, $token);

        if (! $preEnrollment->acceptsReenrollmentResponse()) {
            return $this->inactive($preEnrollment->academicYear);
        }

        $data = app(ConfirmReenrollmentRequest::class)->reenrollmentData();

        // O recado novo vai junto do anterior; o que a secretaria já anotou não muda
        $data['message'] = collect([$preEnrollment->message, $data['message'] ?? null])->filter()->unique()->implode("\n\n") ?: null;

        $preEnrollment->fill([
            ...$data,
            'responded_at' => now(),
            'consent_at' => now(),
        ])->save();

        return redirect()->route('pre-enrollment.done', ['year' => $year])
            ->with(PreEnrollmentController::CODE_SESSION_KEY, $preEnrollment->code)
            ->with(PreEnrollmentController::REENROLLMENT_CHILD_SESSION_KEY, Str::before(Str::squish($preEnrollment->student_name), ' '));
    }

    /** "***.234.214-**": o suficiente para a família reconhecer o cadastro sem expor o CPF. */
    public static function maskDocument(string $document): string
    {
        $digits = preg_replace('/\D/', '', $document);

        return strlen($digits) === 11 ? '***.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-**' : '***';
    }

    private function findPreEnrollment(int $year, string $token): PreEnrollment
    {
        return PreEnrollment::query()
            ->where('access_token', $token)
            ->where('type', PreEnrollmentType::Reenrollment->value)
            ->whereHas('academicYear', fn ($query) => $query->where('year', $year))
            ->with(['academicYear', 'previousEnrollment.classroom'])
            ->firstOrFail();
    }

    private function inactive(AcademicYear $academicYear): View
    {
        return view('pre-enrollment.reenrollment-inactive', ['academicYear' => $academicYear, 'company' => $this->company()]);
    }

    private function company(): ?Company
    {
        return Company::current() ?? Company::query()->orderBy('id')->first();
    }
}
