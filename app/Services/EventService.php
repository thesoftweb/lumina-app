<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Enrollment;
use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class EventService
{
    protected $asaasService;
    protected $invoiceService;

    public function __construct(AsaasService $asaasService, InvoiceService $invoiceService)
    {
        $this->asaasService = $asaasService;
        $this->invoiceService = $invoiceService;
    }

    /**
     * Create an event for a classroom
     */
    public function createEvent(array $data): Event
    {
        return Event::create($data);
    }

    /**
     * Initialize payment for multiple students of same customer
     * Creates a single invoice with total amount and participant for each student
     * Now supports events with multiple classroom options
     */
    public function initializePaymentMultipleStudents(Event $event, $customerId): array
    {
        // Get all active enrollments for this customer across all applicable classrooms
        $enrollments = $event->getCustomerEnrollments($customerId);

        Log::info("EventService: Buscando matrículas para o evento", [
            'event_id' => $event->id,
            'applicable_classrooms' => $event->classrooms()->count(),
            'customer_id' => $customerId,
            'enrollments_found' => $enrollments->count(),
        ]);

        if ($enrollments->isEmpty()) {
            throw new \Exception('Customer is not enrolled in any applicable classrooms for this event');
        }

        // Check if already has pending EventParticipant for any student
        $existingParticipant = EventParticipant::where('event_id', $event->id)
            ->where('customer_id', $customerId)
            ->where('status', 'pending')
            ->first();

        // If payment already initiated, return existing invoice data
        if ($existingParticipant && $existingParticipant->invoice) {
            $invoice = $existingParticipant->invoice;

            // Get Asaas response if available
            $asaasResponse = null;
            if ($invoice->asaas_invoice_id) {
                $asaasResponse = [
                    'id' => $invoice->asaas_invoice_id,
                    'invoiceUrl' => $invoice->invoice_link,
                ];
            }

            Log::info('EventService: Retornando pagamento pendente existente', [
                'event_id' => $event->id,
                'customer_id' => $customerId,
                'invoice_id' => $invoice->id,
            ]);

            // Get student count from enrollments to calculate correct participant_count
            $studentCount = $enrollments->count();

            return [
                'participants' => [$existingParticipant],
                'participant_count' => $studentCount,
                'invoice' => $invoice,
                'asaas_charge_id' => isset($asaasResponse) ? ($asaasResponse['id'] ?? null) : null,
                'payment_url' => isset($asaasResponse) ? ($asaasResponse['invoiceUrl'] ?? null) : null,
                'is_existing_payment' => true,
            ];
        }

        $customer = $enrollments->first()->customer;
        $studentCount = $enrollments->count();
        $totalAmount = $event->amount * $studentCount;

        Log::info("EventService: Calculando valor total", [
            'event_id' => $event->id,
            'event_amount' => $event->amount,
            'student_count' => $studentCount,
            'total_amount' => $totalAmount,
            'applicable_classrooms' => $event->classrooms()->count(),
        ]);

        // Sync customer to Asaas if needed
        $this->asaasService->createOrUpdateCustomer($customer);

        // Create Invoice with total amount
        $invoice = Invoice::create([
            'company_id' => $event->company_id,
            'customer_id' => $customerId,
            'number' => $this->generateInvoiceNumber('EVENT', $event->id),
            'reference' => "EVENT-{$event->id}-{$customerId}",
            'issue_date' => now(),
            'due_date' => $event->due_date,
            'amount' => $totalAmount,
            'balance' => $totalAmount,
            'status' => InvoiceStatus::Open->value,
            'billing_type' => 'event',
            'notes' => "Evento: {$event->name} ({$studentCount} aluno(s))",
        ]);

        // Create Asaas Charge
        $asaasResponse = null;
        try {
            $asaasResponse = $this->asaasService->createCharge($invoice);

            // Update invoice with Asaas data
            if ($asaasResponse && isset($asaasResponse['id'])) {
                $invoice->update([
                    'asaas_invoice_id' => $asaasResponse['id'],
                    'asaas_sync_status' => 'synced',
                    'asaas_synced_at' => now(),
                    'invoice_link' => $asaasResponse['invoiceUrl'] ?? null,
                ]);

                // Try to get PIX QR code if available
                if (isset($asaasResponse['dict'])) {
                    try {
                        $pixResponse = $this->asaasService->getPixQrCode($asaasResponse['id']);
                        if ($pixResponse) {
                            $invoice->update([
                                'invoice_qrcode' => $pixResponse['qrCode'] ?? null,
                            ]);
                        }
                    } catch (\Exception $e) {
                        // PIX QR code generation optional
                    }
                }
            }
        } catch (\Exception $e) {
            $invoice->update([
                'asaas_sync_status' => 'failed',
            ]);
            throw $e;
        }

        // Create EventParticipant for each student
        $participants = [];
        foreach ($enrollments as $enrollment) {
            $participant = EventParticipant::create([
                'event_id' => $event->id,
                'customer_id' => $customerId,
                'invoice_id' => $invoice->id,
                'status' => 'pending',
            ]);
            $participants[] = $participant;
        }

        return [
            'participants' => $participants,
            'participant_count' => $studentCount,
            'invoice' => $invoice,
            'asaas_charge_id' => $asaasResponse['id'] ?? null,
            'payment_url' => $asaasResponse['invoiceUrl'] ?? null,
        ];
    }

    /**
     * Initialize payment on-demand: Create EventParticipant + Invoice + Asaas charge
     */
    public function initializePayment(Event $event, $customerId): array
    {
        // Check if customer is enrolled in the classroom
        $enrollment = $event->classroom->enrollments()
            ->where('customer_id', $customerId)
            ->where('status', 'active')
            ->first();

        if (!$enrollment) {
            throw new \Exception('Customer is not enrolled in this classroom');
        }

        // Check if already has pending EventParticipant
        $existingParticipant = EventParticipant::where('event_id', $event->id)
            ->where('customer_id', $customerId)
            ->where('status', 'pending')
            ->first();

        // If payment already initiated, return existing invoice data
        if ($existingParticipant && $existingParticipant->invoice) {
            $invoice = $existingParticipant->invoice;

            // Get Asaas response if available
            $asaasResponse = null;
            if ($invoice->asaas_invoice_id) {
                $asaasResponse = [
                    'id' => $invoice->asaas_invoice_id,
                    'invoiceUrl' => $invoice->invoice_link,
                ];
            }

            Log::info('EventService: Retornando pagamento pendente existente (método simples)', [
                'event_id' => $event->id,
                'customer_id' => $customerId,
                'invoice_id' => $invoice->id,
            ]);

            return [
                'participants' => [$existingParticipant],
                'participant_count' => 1,
                'invoice' => $invoice,
                'asaas_charge_id' => isset($asaasResponse) ? ($asaasResponse['id'] ?? null) : null,
                'payment_url' => isset($asaasResponse) ? ($asaasResponse['invoiceUrl'] ?? null) : null,
                'is_existing_payment' => true,
            ];
        }

        $customer = $enrollment->customer;

        // Sync customer to Asaas if needed
        $this->asaasService->createOrUpdateCustomer($customer);

        // Create Invoice
        $invoice = Invoice::create([
            'company_id' => $event->company_id,
            'customer_id' => $customerId,
            'number' => $this->generateInvoiceNumber('EVENT', $event->id),
            'reference' => "EVENT-{$event->id}-{$customerId}",
            'issue_date' => now(),
            'due_date' => $event->due_date,
            'amount' => $event->amount,
            'balance' => $event->amount,
            'status' => InvoiceStatus::Open->value,
            'billing_type' => 'event',
            'notes' => "Evento: {$event->name}",
        ]);

        // Create Asaas Charge
        $asaasResponse = null;
        try {
            $asaasResponse = $this->asaasService->createCharge($invoice);

            // Update invoice with Asaas data
            if ($asaasResponse && isset($asaasResponse['id'])) {
                $invoice->update([
                    'asaas_invoice_id' => $asaasResponse['id'],
                    'asaas_sync_status' => 'synced',
                    'asaas_synced_at' => now(),
                    'invoice_link' => $asaasResponse['invoiceUrl'] ?? null,
                ]);

                // Try to get PIX QR code if available
                if (isset($asaasResponse['dict'])) {
                    try {
                        $pixResponse = $this->asaasService->getPixQrCode($asaasResponse['id']);
                        if ($pixResponse) {
                            $invoice->update([
                                'invoice_qrcode' => $pixResponse['qrCode'] ?? null,
                            ]);
                        }
                    } catch (\Exception $e) {
                        // PIX QR code generation optional
                    }
                }
            }
        } catch (\Exception $e) {
            $invoice->update([
                'asaas_sync_status' => 'failed',
            ]);
            throw $e;
        }

        // Create EventParticipant
        $participant = EventParticipant::create([
            'event_id' => $event->id,
            'customer_id' => $customerId,
            'invoice_id' => $invoice->id,
            'status' => 'pending',
        ]);

        return [
            'participant' => $participant,
            'invoice' => $invoice,
            'asaas_charge_id' => $asaasResponse['id'] ?? null,
            'payment_url' => $asaasResponse['invoiceUrl'] ?? null,
        ];
    }

    /**
     * Mark participant as paid and update invoice
     */
    public function markParticipantAsPaid(EventParticipant $participant, array $paymentData = []): EventParticipant
    {
        $participant->update([
            'status' => 'paid',
            'paid_at' => $paymentData['paid_at'] ?? now(),
        ]);

        // Update invoice status
        if ($participant->invoice) {
            $participant->invoice->update([
                'status' => InvoiceStatus::Paid->value,
                'balance' => 0,
            ]);

            // Create payment record
            InvoicePayment::create([
                'invoice_id' => $participant->invoice->id,
                'payment_date' => $paymentData['paid_at'] ?? now(),
                'amount' => $participant->invoice->amount,
                'payment_method' => $paymentData['payment_method'] ?? 'PIX',
                'reference' => $paymentData['pix_id'] ?? $paymentData['asaas_payment_id'] ?? null,
                'asaas_payment_id' => $paymentData['asaas_payment_id'] ?? null,
                'asaas_sync_status' => 'synced',
                'asaas_synced_at' => now(),
            ]);
        }

        return $participant;
    }

    /**
     * Get customers eligible to pay for an event
     * Considers all applicable classrooms
     */
    public function getEligibleCustomers(Event $event): Collection
    {
        $classroomIds = $event->classrooms()->pluck('classrooms.id')->toArray();

        return Enrollment::whereIn('classroom_id', $classroomIds)
            ->where('status', 'active')
            ->with('customer')
            ->get()
            ->pluck('customer')
            ->unique('id');
    }

    /**
     * Get event statistics
     * Considers all applicable classrooms
     */
    public function getEventStats(Event $event): array
    {
        $classroomIds = $event->classrooms()->pluck('classrooms.id')->toArray();

        $totalEligible = Enrollment::whereIn('classroom_id', $classroomIds)
            ->where('status', 'active')
            ->count();

        $paidCount = $event->paidParticipants()->count();
        $totalCollected = $paidCount * $event->amount;

        return [
            'total_eligible' => $totalEligible,
            'paid_count' => $paidCount,
            'pending_count' => $event->participants()->where('status', 'pending')->count(),
            'total_collected' => $totalCollected,
            'collection_rate' => $totalEligible > 0
                ? round(($paidCount / $totalEligible) * 100, 2)
                : 0,
        ];
    }

    /**
     * Close an event
     */
    public function closeEvent(Event $event): Event
    {
        $event->update(['status' => 'closed']);
        return $event;
    }

    /**
     * Generate unique invoice number
     */
    protected function generateInvoiceNumber(string $prefix, int $eventId): string
    {
        $timestamp = now()->format('YmdHis');
        return "{$prefix}-{$eventId}-{$timestamp}";
    }
}
