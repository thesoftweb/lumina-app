<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Enrollment;
use App\Services\AgendaService;

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

    private function activeClassroomIds(Customer $customer)
    {
        return Enrollment::whereHas('student', function ($query) use ($customer) {
            $query->where('customer_id', $customer->id);
        })
            ->where('status', 'active')
            ->pluck('classroom_id')
            ->filter()
            ->unique();
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

        $classroomIds = $this->activeClassroomIds($customer);

        if ($classroomIds->isEmpty()) {
            return response()->json(['error' => 'No active enrollment found'], 404);
        }

        $agendas = $this->agendaService->getStudentAgendasForClassrooms($classroomIds);

        return view('portal.agendas.index', [
            'agendas' => $agendas,
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

        $classroomIds = $this->activeClassroomIds($customer);

        if ($classroomIds->isEmpty()) {
            return response()->json(['error' => 'No active enrollment found'], 404);
        }

        $agendas = $this->agendaService->getStudentAgendasForClassrooms(
            $classroomIds,
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

        $classroomIds = $this->activeClassroomIds($customer);

        if ($classroomIds->isEmpty()) {
            return response()->json(['error' => 'No active enrollment found'], 404);
        }

        $agenda = $this->agendaService->getStudentAgendasForClassrooms($classroomIds)
            ->where('id', $id)
            ->first();

        if (!$agenda) {
            return response()->json(['error' => 'Agenda not found'], 404);
        }

        return view('portal.agendas.show', [
            'agenda' => $agenda,
        ]);
    }
}
