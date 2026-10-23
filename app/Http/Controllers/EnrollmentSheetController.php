<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EnrollmentSheetController extends Controller
{
    /**
     * Ficha de matrícula em A4, pronta para imprimir ou salvar em PDF pelo navegador e colher assinaturas.
     */
    public function show(Request $request, Enrollment $enrollment)
    {
        Gate::authorize('view', $enrollment);

        $enrollment->load(['student.customer.city.state', 'classroom.level', 'academicYear', 'plan']);
        $user = $request->user();
        $company = $user->company ?? (Company::count() === 1 ? Company::first() : null);

        return view('enrollments.sheet', [
            'enrollment' => $enrollment,
            'student' => $enrollment->student,
            'customer' => $enrollment->student?->customer,
            'company' => $company?->loadMissing('city.state'),
            'tuition' => $enrollment->tuitionBreakdown(),
            'autoPrint' => $request->boolean('print'),
            'generatedBy' => $user->name,
        ]);
    }
}
