<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Services\ReportCardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;

/** Boletim em PDF (gerado em PHP, sem navegador no servidor): ver no navegador ou baixar. */
class ReportCardController extends Controller
{
    public function show(Enrollment $enrollment, ReportCardService $service)
    {
        Gate::authorize('view', $enrollment->student);

        return $this->pdf($enrollment, $service)->stream($this->filename($enrollment));
    }

    public function download(Enrollment $enrollment, ReportCardService $service)
    {
        Gate::authorize('view', $enrollment->student);

        return $this->pdf($enrollment, $service)->download($this->filename($enrollment));
    }

    private function pdf(Enrollment $enrollment, ReportCardService $service): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('report-cards.pdf', $service->forEnrollment($enrollment))->setPaper('a4');
    }

    private function filename(Enrollment $enrollment): string
    {
        return 'boletim-'.str($enrollment->student->name)->slug().'-'.($enrollment->academicYear?->year ?? $enrollment->id).'.pdf';
    }
}
