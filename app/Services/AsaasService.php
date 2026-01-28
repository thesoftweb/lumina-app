<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class AsaasService
{
    private Client $client;
    private ?string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('asaas.api_key') ?: '';
        $environment = config('asaas.environment', 'sandbox');

        $endpoints = config('asaas.endpoints', [
            'sandbox' => 'https://sandbox.asaas.com/api/v3',
            'production' => 'https://api.asaas.com/api/v3',
        ]);

        $this->baseUrl = $endpoints[$environment] ?? 'https://sandbox.asaas.com/api/v3';

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => config('asaas.timeout', 30),
            'connect_timeout' => config('asaas.connect_timeout', 10),
        ]);
    }

    /**
     * Criar ou sincronizar cliente no Asaas
     *
     * @param Customer $customer
     * @return array|null
     */
    public function createOrUpdateCustomer(Customer $customer): ?array
    {
        try {
            // Se já existe ID Asaas, atualizar
            if ($customer->asaas_customer_id) {
                return $this->updateCustomer($customer);
            }

            // Caso contrário, criar novo
            $payload = $this->buildCustomerPayload($customer);

            $response = $this->client->post('/customers', [
                'headers' => $this->getHeaders(),
                'json' => $payload,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['id'])) {
                $customer->update(['asaas_customer_id' => $data['id']]);
                Log::info("Asaas customer created for customer {$customer->id}: {$data['id']}");
                return $data;
            }

            return null;
        } catch (GuzzleException $e) {
            Log::error("Asaas error creating customer {$customer->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Atualizar cliente no Asaas
     *
     * @param Customer $customer
     * @return array|null
     */
    private function updateCustomer(Customer $customer): ?array
    {
        try {
            $payload = $this->buildCustomerPayload($customer);

            $response = $this->client->put("/customers/{$customer->asaas_customer_id}", [
                'headers' => $this->getHeaders(),
                'json' => $payload,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            Log::info("Asaas customer updated: {$customer->asaas_customer_id}");
            return $data;
        } catch (GuzzleException $e) {
            Log::error("Asaas error updating customer {$customer->asaas_customer_id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Criar cobrança no Asaas
     *
     * @param Invoice $invoice
     * @return array|null
     */
    public function createCharge(Invoice $invoice): ?array
    {
        try {
            // Garantir que cliente existe no Asaas
            if (!$invoice->customer->asaas_customer_id) {
                $this->createOrUpdateCustomer($invoice->customer);
            }

            $payload = $this->buildChargePayload($invoice);

            $response = $this->client->post('/payments', [
                'headers' => $this->getHeaders(),
                'json' => $payload,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['id'])) {
                $invoice->update([
                    'asaas_invoice_id' => $data['id'],
                    'asaas_sync_status' => 'pending',
                ]);

                Log::info("Asaas charge created for invoice {$invoice->id}: {$data['id']}");
                return $data;
            }

            return null;
        } catch (GuzzleException $e) {
            Log::error("Asaas error creating charge for invoice {$invoice->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obter detalhes do pagamento no Asaas
     *
     * @param Invoice $invoice
     * @return array|null
     */
    public function getPayment(Invoice $invoice): ?array
    {
        try {
            if (!$invoice->asaas_invoice_id) {
                return null;
            }

            $response = $this->client->get("/payments/{$invoice->asaas_invoice_id}", [
                'headers' => $this->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data;
        } catch (GuzzleException $e) {
            Log::error("Asaas error fetching payment {$invoice->asaas_invoice_id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Sincronizar status de pagamento do Asaas
     *
     * @param Invoice $invoice
     * @return bool
     */
    public function syncPaymentStatus(Invoice $invoice): bool
    {
        try {
            $paymentData = $this->getPayment($invoice);

            if (!$paymentData) {
                return false;
            }

            $status = $paymentData['status'] ?? null;

            // Mapear status Asaas para status local
            $statusMap = [
                'PENDING' => 'open',
                'CONFIRMED' => 'partial',
                'RECEIVED' => 'paid',
                'OVERDUE' => 'overdue',
                'REFUNDED' => 'paid',
                'PARTIALLY_RECEIVED' => 'partial',
                'DISPUTED' => 'open',
                'AWAITING_RISK_ANALYSIS' => 'open',
                'RISK_REJECTED' => 'canceled',
                'DELETED' => 'canceled',
            ];

            $newStatus = $statusMap[$status] ?? 'open';

            // Se pagamento recebido, registrar na tabela de pagamentos
            if ($status === 'RECEIVED' && $invoice->status !== 'paid') {
                $paymentService = new PaymentService();
                $paymentDate = $paymentData['confirmedDate'] ?? now()->format('Y-m-d');
                $paymentReference = $paymentData['id'] ?? 'asaas-' . $invoice->id;

                $paymentService->payFullInvoice(
                    $invoice,
                    'asaas',
                    $paymentReference,
                    "Pagamento sincronizado do Asaas em {$paymentDate}"
                );
            } else {
                // Apenas atualizar status
                $invoice->update([
                    'status' => $newStatus,
                    'asaas_sync_status' => 'synced',
                ]);
            }

            Log::info("Asaas payment synced for invoice {$invoice->id}: {$status}");
            return true;
        } catch (\Exception $e) {
            Log::error("Asaas error syncing payment for invoice {$invoice->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Listar cobranças/pagamentos do Asaas
     *
     * @param Customer|null $customer
     * @param int $limit
     * @return array|null
     */
    public function listPayments(?Customer $customer = null, int $limit = 100): ?array
    {
        try {
            $query = ['limit' => $limit];

            if ($customer && $customer->asaas_customer_id) {
                $query['customer'] = $customer->asaas_customer_id;
            }

            $response = $this->client->get('/payments', [
                'headers' => $this->getHeaders(),
                'query' => $query,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['data'] ?? [];
        } catch (GuzzleException $e) {
            Log::error("Asaas error listing payments: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Cancelar cobrança no Asaas
     *
     * @param Invoice $invoice
     * @return bool
     */
    public function cancelCharge(Invoice $invoice): bool
    {
        try {
            if (!$invoice->asaas_invoice_id) {
                return false;
            }

            $response = $this->client->delete("/payments/{$invoice->asaas_invoice_id}", [
                'headers' => $this->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if ($data['deleted'] ?? false) {
                $invoice->update([
                    'status' => 'canceled',
                    'asaas_sync_status' => 'canceled',
                ]);

                Log::info("Asaas charge canceled for invoice {$invoice->id}");
                return true;
            }

            return false;
        } catch (GuzzleException $e) {
            Log::error("Asaas error canceling charge {$invoice->asaas_invoice_id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obter URL do boleto/PIX
     *
     * @param Invoice $invoice
     * @return array|null
     */
    public function getPaymentLinks(Invoice $invoice): ?array
    {
        try {
            $paymentData = $this->getPayment($invoice);

            if (!$paymentData) {
                return null;
            }

            return [
                'boleto_url' => $paymentData['bankSlip']['url'] ?? null,
                'boleto_barcode' => $paymentData['bankSlip']['barCode'] ?? null,
                'pix_url' => $paymentData['pixQrCode']['url'] ?? null,
                'pix_qr_code' => $paymentData['pixQrCode']['qrCode'] ?? null,
                'pix_copy_paste' => $paymentData['pixQrCode']['encodedImage'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error("Asaas error getting payment links for invoice {$invoice->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Construir payload do cliente para Asaas
     *
     * @param Customer $customer
     * @return array
     */
    private function buildCustomerPayload(Customer $customer): array
    {
        return [
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'cpfCnpj' => $this->cleanDocument($customer->document),
            'addressStreet' => $customer->address,
            'addressNumber' => $customer->address_number ?? '',
            'addressComplement' => $customer->address_complement ?? '',
            'addressCity' => $customer->city?->name ?? '',
            'addressState' => $customer->city?->state ?? '',
            'addressPostalCode' => $this->cleanPostalCode($customer->postal_code ?? ''),
            'externalReference' => (string) $customer->id,
        ];
    }

    /**
     * Construir payload de cobrança para Asaas
     *
     * @param Invoice $invoice
     * @return array
     */
    private function buildChargePayload(Invoice $invoice): array
    {
        $payload = [
            'customer' => $invoice->customer->asaas_customer_id,
            'description' => $invoice->getDescription(),
            'value' => (float) $invoice->balance ?: $invoice->amount,
            'dueDate' => $invoice->due_date->format('Y-m-d'),
            'externalReference' => (string) $invoice->id,
        ];

        // Adicionar PIX dinâmico se configurado
        if (config('asaas.invoice.generate_pix', true)) {
            $payload['pixGenerationType'] = 'DYNAMIC';
        }

        // Notificar cliente se configurado
        if (config('asaas.invoice.notify_customer', true)) {
            $payload['notifyCustomer'] = true;
        }

        return $payload;
    }

    /**
     * Limpar documento (CPF/CNPJ)
     *
     * @param string $document
     * @return string
     */
    private function cleanDocument(string $document): string
    {
        return preg_replace('/[^0-9]/', '', $document);
    }

    /**
     * Limpar CEP
     *
     * @param string $postalCode
     * @return string
     */
    private function cleanPostalCode(string $postalCode): string
    {
        return preg_replace('/[^0-9]/', '', $postalCode);
    }

    /**
     * Obter headers para requisições
     *
     * @return array
     */
    private function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }
}
