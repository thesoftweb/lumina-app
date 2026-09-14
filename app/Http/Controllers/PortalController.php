<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Services\AgendaService;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function login()
    {
        return view('portal.login');
    }

    public function accessPortal(Request $request)
    {
        $request->validate([
            'document' => 'required|string|min:11',
        ], [
            'document.required' => 'O documento é obrigatório.',
            'document.min' => 'O documento deve ter pelo menos 11 caracteres.',
        ]);

        // Remove formatting from document
        // $document = preg_replace('/\D/', '', $request->document);

        // Search customer by document
        $customer = Customer::where('document', $request->document)->first();

        if (!$customer) {
            return back()->withErrors(['document' => 'Nenhum cadastro foi localizado com o Documento informado.'])->withInput();
        }

        // Search enrollments linked to this customer's students
        $enrollments = Enrollment::whereHas('student', function ($query) use ($customer) {
            $query->where('customer_id', $customer->id);
        })->get();

        if ($enrollments->isEmpty()) {
            return back()->withErrors(['document' => 'Nenhuma matrícula foi localizada para este documento.'])->withInput();
        }

        // Store document in session
        session(['customer_document' => $request->document]);

        return redirect()->route('portal.show');
    }

    public function showStudent(AgendaService $agendaService)
    {
        return $this->showPortalPage('home', $agendaService);
    }

    public function showEnrollments(AgendaService $agendaService)
    {
        return $this->showPortalPage('enrollments', $agendaService);
    }

    public function showInvoices(AgendaService $agendaService)
    {
        return $this->showPortalPage('invoices', $agendaService);
    }

    private function showPortalPage(string $portalPage, AgendaService $agendaService)
    {
        $document = session('customer_document');

        if (!$document) {
            return redirect()->route('portal.login')->withErrors(['document' => 'Session expired. Please login again.']);
        }

        // Search customer by document
        $customer = Customer::where('document', $document)->first();

        if (!$customer) {
            return redirect()->route('portal.login')->withErrors(['document' => 'Invalid session. Please login again.']);
        }

        // Search enrollments for this customer's students
        $enrollments = Enrollment::whereHas('student', function ($query) use ($customer) {
            $query->where('customer_id', $customer->id);
        })->with(['student', 'classroom'])->get();

        $activeClassroomIds = $enrollments
            ->filter(fn (Enrollment $enrollment) => $enrollment->status?->value === 'active')
            ->pluck('classroom_id')
            ->filter()
            ->unique();

        $agendas = $agendaService->getStudentAgendasForClassrooms($activeClassroomIds);

        // Search invoices directly by customer_id
        $invoices = Invoice::where('customer_id', $customer->id)
            ->orderBy('due_date', 'asc')
            ->get();

        // Format document for display
        $formattedDocument = substr($document, 0, 3) . '.' . substr($document, 3, 3) . '.' . substr($document, 6, 3) . '-' . substr($document, 9, 2);

        return view('portal.student', [
            'document' => $formattedDocument,
            'enrollments' => $enrollments,
            'invoices' => $invoices,
            'agendas' => $agendas,
            'portalPage' => $portalPage,
        ]);
    }
}
