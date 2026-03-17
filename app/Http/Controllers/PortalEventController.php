<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventService;
use Illuminate\Support\Facades\Auth;

class PortalEventController extends Controller
{
    protected $eventService;

    public function __construct(EventService $eventService)
    {
        $this->eventService = $eventService;
    }

    /**
     * Display list of active events for student's classrooms
     */
    public function index()
    {
        $user = Auth::user();

        if (!$user->student) {
            return view('portal.events', ['events' => collect()]);
        }

        // Get classrooms where student is enrolled
        $classroomIds = $user->student->enrollments()
            ->where('status', 'active')
            ->pluck('classroom_id')
            ->unique();

        // Get active events for those classrooms
        $events = Event::whereIn('classroom_id', $classroomIds)
            ->where('status', 'active')
            ->where('due_date', '>=', now())
            ->with(['classroom', 'participants'])
            ->orderBy('due_date')
            ->get();

        return view('portal.events', compact('events'));
    }

    /**
     * Handle payment initialization on-demand
     */
    public function store(Event $event)
    {
        $user = Auth::user();

        if (!$user->student) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $customer = $user->student->customer;

        if (!$customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        try {
            $paymentData = $this->eventService->initializePayment($event, $customer->id);

            return response()->json([
                'success' => true,
                'participant' => $paymentData['participant'],
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
        $user = Auth::user();

        if (!$user->student || !$user->student->customer) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $participant = $event->participants()
            ->where('customer_id', $user->student->customer->id)
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
