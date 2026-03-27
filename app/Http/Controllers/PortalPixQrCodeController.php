<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\AsaasService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PortalPixQrCodeController extends Controller
{
    protected AsaasService $asaasService;

    public function __construct(AsaasService $asaasService)
    {
        $this->asaasService = $asaasService;
    }

    /**
     * Obter QR Code PIX para um invoice
     */
    public function show(Invoice $invoice): JsonResponse
    {
        // If not synced yet, try to sync now
        if (!$invoice->asaas_invoice_id) {
            Log::info('Portal PIX: Tentando sincronizar invoice que não foi sincronizada', [
                'invoice_id' => $invoice->id,
            ]);

            try {
                $asaasResponse = $this->asaasService->createCharge($invoice);

                if ($asaasResponse && isset($asaasResponse['id'])) {
                    $invoice->update([
                        'asaas_invoice_id' => $asaasResponse['id'],
                        'asaas_sync_status' => 'synced',
                        'asaas_synced_at' => now(),
                        'invoice_link' => $asaasResponse['invoiceUrl'] ?? null,
                    ]);

                    Log::info('Portal PIX: Invoice sincronizada com sucesso', [
                        'invoice_id' => $invoice->id,
                        'asaas_id' => $asaasResponse['id'],
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Erro ao sincronizar fatura com Asaas. Tente novamente.',
                    ], 400);
                }
            } catch (\Exception $e) {
                Log::error('Portal PIX: Erro ao sincronizar invoice', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao sincronizar fatura com Asaas. Tente novamente.',
                ], 400);
            }
        }

        $pixData = $this->asaasService->getPixQrCode($invoice);

        if (!$pixData) {
            return response()->json([
                'success' => false,
                'message' => 'Não foi possível obter o QR Code PIX. Tente novamente mais tarde.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'reference' => $invoice->reference,
                'description' => $pixData['description'],
                'amount' => number_format($invoice->amount, 2, ',', '.'),
                'dueDate' => $invoice->due_date?->format('d/m/Y'),
                'encodedImage' => $pixData['encodedImage'],
                'payload' => $pixData['payload'],
                'expirationDate' => $pixData['expirationDate'],
            ],
        ]);
    }
}
