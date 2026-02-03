<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\AsaasService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
        if (!$invoice->asaas_invoice_id) {
            return response()->json([
                'success' => false,
                'message' => 'Fatura não foi sincronizada com o Asaas ainda',
            ], 404);
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
