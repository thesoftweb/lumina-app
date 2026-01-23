<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Customer;

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
        session(['customer_document' => $document]);

        return redirect()->route('portal.show');
    }

    public function showStudent(Request $request)
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
        })->get();

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
        ]);
    }
}
