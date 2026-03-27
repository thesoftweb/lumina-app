<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Services\EventService;
use Illuminate\Support\Facades\Session;

class PortalEventController extends Controller
{
    protected $eventService;

    public function __construct(EventService $eventService)
    {
        $this->eventService = $eventService;
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
     * Display list of active events available for student's enrolled classrooms
     */
    public function index()
    {
        $customer = $this->getCustomerFromSession();

        if (!$customer) {
            return view('portal.events', ['events' => collect()]);
        }

        // Get classrooms where student (of this customer) is enrolled
        $classroomIds = Enrollment::whereHas('student', function ($query) use ($customer) {
            $query->where('customer_id', $customer->id);
        })
        ->where('status', 'active')
        ->pluck('classroom_id')
        ->unique();

        // Get active events available to any of the customer's classrooms
        $events = Event::whereHas('classrooms', function ($query) use ($classroomIds) {
            $query->whereIn('classrooms.id', $classroomIds);
        })
            ->where('status', 'active')
            ->where('due_date', '>=', now())
            ->with(['classrooms', 'participants'])
            ->orderBy('due_date')
            ->get();

        return view('portal.events', compact('events'));
    }

    /**
     * Handle payment initialization on-demand
     */
    public function store(Event $event)
    {
        $customer = $this->getCustomerFromSession();

        if (!$customer) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $paymentData = $this->eventService->initializePaymentMultipleStudents($event, $customer->id);

            return response()->json([
                'success' => true,
                'participants' => $paymentData['participants'],
                'participant_count' => $paymentData['participant_count'],
                'invoice' => $paymentData['invoice'],
                'payment_url' => $paymentData['payment_url'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get payment details (PIX QR code, links, etc)
     */
    public function paymentLink(Event $event)
    {
        $customer = $this->getCustomerFromSession();

        if (!$customer) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $participant = $event->participants()
            ->where('customer_id', $customer->id)
            ->first();

        if (!$participant || !$participant->invoice) {
            return response()->json(['error' => 'Payment not found'], 404);
        }

        $invoice = $participant->invoice;

        return response()->json([
            'id' => $invoice->id,
            'asaas_id' => $invoice->asaas_invoice_id,
            'amount' => $invoice->amount,
            'due_date' => $invoice->due_date->toDateString(),
            'invoice_link' => $invoice->invoice_link,
            'invoice_qrcode' => $invoice->invoice_qrcode,
            'status' => $invoice->status,
        ]);
    }
}
