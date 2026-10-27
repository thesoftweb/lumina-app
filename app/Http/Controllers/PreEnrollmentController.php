<?php

namespace App\Http\Controllers;

use App\Enums\DegreeOfKinship;
use App\Enums\LeadSource;
use App\Enums\PreEnrollmentStatus;
use App\Http\Requests\StorePreEnrollmentRequest;
use App\Models\AcademicYear;
use App\Models\AdmissionOffer;
use App\Models\Company;
use App\Models\PreEnrollment;
use App\Services\Admissions\PreEnrollmentIntake;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Link público da pré-matrícula: uma ficha por ano letivo, com as vagas e os valores daquele ano. */
class PreEnrollmentController extends Controller
{
    public const CODE_SESSION_KEY = 'pre_enrollment_code';

    /** Primeiro nome da criança quando a confirmação veio do link de rematrícula. */
    public const REENROLLMENT_CHILD_SESSION_KEY = 'pre_enrollment_reenrollment_child';

    /** Vagas ativas do ano, com as fichas que já ocupam cada uma (desistências não contam). */
    public static function offers(AcademicYear $academicYear)
    {
        return AdmissionOffer::query()->active()->where('academic_year_id', $academicYear->getKey())
            ->with(['plan', 'level'])->withCount(['preEnrollments as taken_count' => fn ($query) => $query->where('status', '!=', PreEnrollmentStatus::Lost->value)])
            ->get();
    }

    public function index(): View|RedirectResponse
    {
        $years = AcademicYear::query()->acceptingPreEnrollments()->orderBy('year')->get();

        if ($years->count() === 1) {
            return redirect()->route('pre-enrollment.create', ['year' => $years->first()->year, ...$this->utm(request())]);
        }

        return view('pre-enrollment.index', ['years' => $years, 'company' => $this->company()]);
    }

    public function create(Request $request, int $year): View
    {
        $academicYear = $this->findYear($year);

        if (! $academicYear->acceptsPreEnrollments()) {
            return $this->closed($academicYear);
        }

        return view('pre-enrollment.create', [
            'academicYear' => $academicYear,
            'offers' => self::offers($academicYear),
            'kinships' => DegreeOfKinship::cases(),
            'sources' => LeadSource::cases(),
            'utm' => $this->utm($request),
            'company' => $this->company(),
        ]);
    }

    public function store(Request $request, int $year, PreEnrollmentIntake $intake): RedirectResponse
    {
        $academicYear = $this->findYear($year);

        if (! $academicYear->acceptsPreEnrollments()) {
            return redirect()->route('pre-enrollment.create', ['year' => $year]);
        }

        // Honeypot: robô preenche o campo escondido; recebe a mesma tela de sucesso, sem gravar nada
        if (filled($request->input('website'))) {
            return redirect()->route('pre-enrollment.done', ['year' => $year])
                ->with(self::CODE_SESSION_KEY, PreEnrollment::newCode($academicYear->year));
        }

        $form = app(StorePreEnrollmentRequest::class);
        $preEnrollment = $intake->submit($academicYear, $form->preEnrollmentData(), $request->ip());

        return redirect()->route('pre-enrollment.done', ['year' => $year])
            ->with(self::CODE_SESSION_KEY, $preEnrollment->code);
    }

    public function done(Request $request, int $year): View|RedirectResponse
    {
        $academicYear = $this->findYear($year);
        $code = $request->session()->get(self::CODE_SESSION_KEY);

        if (! $code) {
            return redirect()->route('pre-enrollment.create', ['year' => $year]);
        }

        return view('pre-enrollment.done', [
            'academicYear' => $academicYear,
            'code' => $code,
            'reenrollmentChild' => $request->session()->get(self::REENROLLMENT_CHILD_SESSION_KEY),
            'company' => $this->company(),
        ]);
    }

    private function closed(AcademicYear $academicYear): View
    {
        return view('pre-enrollment.index', [
            'years' => AcademicYear::query()->acceptingPreEnrollments()->orderBy('year')->get(),
            'closedYear' => $academicYear,
            'company' => $this->company(),
        ]);
    }

    private function findYear(int $year): AcademicYear
    {
        return AcademicYear::query()->where('year', $year)->orderBy('id')->firstOrFail();
    }

    private function company(): ?Company
    {
        return Company::current() ?? Company::query()->orderBy('id')->first();
    }

    /** @return array<string, string> parâmetros de campanha da URL, repassados à ficha */
    private function utm(Request $request): array
    {
        return collect(['utm_source', 'utm_medium', 'utm_campaign'])
            ->filter(fn (string $key): bool => filled($request->query($key)) && is_string($request->query($key)))
            ->mapWithKeys(fn (string $key): array => [$key => Str::limit($request->query($key), 100, '')])
            ->all();
    }
}
