<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Services\ReportCardService;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPdf\Facades\Pdf;

class ReportCardController extends Controller
{
    public function show(Enrollment $enrollment, ReportCardService $service)
    {
        Gate::authorize('view', $enrollment->student);

        return view('report-cards.show', $service->forEnrollment($enrollment));
    }

    public function pdf(Enrollment $enrollment, ReportCardService $service)
    {
        Gate::authorize('view', $enrollment->student);

        return Pdf::view('report-cards.pdf', $service->forEnrollment($enrollment))
            ->format('a4')
            ->name('boletim-'.str($enrollment->student->name)->slug().'.pdf');
    }
}
