<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Enrollment;
use App\Services\AgendaService;
use Illuminate\Support\Facades\Session;

class PortalAgendaController extends Controller
{
    protected $agendaService;

    public function __construct(AgendaService $agendaService)
    {
        $this->agendaService = $agendaService;
    }

    /**
     * Get customer from session
     */
    private function getCustomerFromSession()
    {
        $document = session('customer_document');

        if (!$document) {
            return null;
        }

        return Customer::where('document', $document)->first();
    }

    /**
     * List all agendas for the student
     */
    public function index()
    {
        $customer = $this->getCustomerFromSession();

        if (!$customer) {
            return response()->json(['error' => 'Student not found'], 404);
        }

        // Get first active enrollment
        $enrollment = Enrollment::whereHas('student', function ($query) use ($customer) {
            $query->where('customer_id', $customer->id);
        })
            ->where('status', 'active')
            ->first();

        if (!$enrollment) {
            return response()->json(['error' => 'No active enrollment found'], 404);
        }

        $agendas = $this->agendaService->getStudentAgendasPaginated(
            $enrollment->classroom_id,
            perPage: 15,
            upcoming: false
        );

        return view('portal.agendas.index', [
            'agendas' => $agendas,
            'enrollment' => $enrollment,
        ]);
    }

    /**
     * Get upcoming agendas
     */
    public function upcoming()
    {
        $customer = $this->getCustomerFromSession();

        if (!$customer) {
            return response()->json(['error' => 'Student not found'], 404);
        }

        // Get first active enrollment
        $enrollment = Enrollment::whereHas('student', function ($query) use ($customer) {
            $query->where('customer_id', $customer->id);
        })
            ->where('status', 'active')
            ->first();

        if (!$enrollment) {
            return response()->json(['error' => 'No active enrollment found'], 404);
        }

        $agendas = $this->agendaService->getStudentAgendas(
            $enrollment->classroom_id,
            upcoming: true
        );

        return response()->json([
            'data' => $agendas,
            'count' => $agendas->count(),
        ]);
    }

    /**
     * Show single agenda
     */
    public function show($id)
    {
        $customer = $this->getCustomerFromSession();

        if (!$customer) {
            return response()->json(['error' => 'Student not found'], 404);
        }

        // Get first active enrollment
        $enrollment = Enrollment::whereHas('student', function ($query) use ($customer) {
            $query->where('customer_id', $customer->id);
        })
            ->where('status', 'active')
            ->first();

        if (!$enrollment) {
            return response()->json(['error' => 'No active enrollment found'], 404);
        }

        $agenda = $this->agendaService->getStudentAgendas($enrollment->classroom_id)
            ->where('id', $id)
            ->first();

        if (!$agenda) {
            return response()->json(['error' => 'Agenda not found'], 404);
        }

        return response()->json($agenda);
    }
}
