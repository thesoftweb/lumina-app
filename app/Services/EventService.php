<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Collection;

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
            ->first();

        if ($existingParticipant && $existingParticipant->status === 'pending') {
            throw new \Exception('Payment already initiated for this customer');
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
        try {
            $asaasResponse = $this->asaasService->createCharge([
                'customer' => $customer->asaas_customer_id,
                'billingType' => 'UNDEFINED',
                'dueDate' => $event->due_date->format('Y-m-d'),
                'value' => (float) $event->amount,
                'description' => $event->name,
                'externalReference' => "EVENT-{$event->id}-{$customerId}",
                'pixKey' => null,
            ]);

            // Update invoice with Asaas data
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
     * Get customers eligible to pay for an event (enrolled in classroom)
     */
    public function getEligibleCustomers(Event $event): Collection
    {
        return $event->classroom->enrollments()
            ->where('status', 'active')
            ->with('customer')
            ->get()
            ->pluck('customer')
            ->unique('id');
    }

    /**
     * Get event statistics
     */
    public function getEventStats(Event $event): array
    {
        $paidCount = $event->paidParticipants()->count();
        $totalCollected = $paidCount * $event->amount;

        return [
            'total_eligible' => $event->classroom->enrollments()->where('status', 'active')->count(),
            'paid_count' => $paidCount,
            'pending_count' => $event->participants()->where('status', 'pending')->count(),
            'total_collected' => $totalCollected,
            'collection_rate' => $event->classroom->enrollments()->where('status', 'active')->count() > 0
                ? round(($paidCount / $event->classroom->enrollments()->where('status', 'active')->count()) * 100, 2)
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
