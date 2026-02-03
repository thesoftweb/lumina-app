<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AsaasService
{
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

            // Procurar cliente existente por CPF no Asaas
            if ($customer->document) {
                $existingCustomer = $this->findCustomerByCpf($customer->document);
                if ($existingCustomer) {
                    $customer->update(['asaas_customer_id' => $existingCustomer['id']]);
                    Log::info("Asaas customer found for customer {$customer->id}: {$existingCustomer['id']} (CPF match)");
                    return $existingCustomer;
                }
            }

            // Caso contrário, criar novo
            $payload = $this->buildCustomerPayload($customer);

            $response = Http::withHeaders($this->getHeaders())
                ->timeout(config('asaas.timeout', 30))
                ->connectTimeout(config('asaas.connect_timeout', 10))
                ->post("{$this->baseUrl}/customers", $payload);

            if ($response->failed()) {
                $error = $response->json('errors.0.detail') ?? $response->json('message') ?? 'Unknown error';
                Log::error("Asaas API error creating customer {$customer->id}: {$error}", $response->json() ?? []);
                return null;
            }

            $data = $response->json();

            if (isset($data['id'])) {
                $customer->update(['asaas_customer_id' => $data['id']]);
                Log::info("Asaas customer created for customer {$customer->id}: {$data['id']}");
                return $data;
            }

            Log::warning("Asaas response without ID for customer {$customer->id}", $data ?? []);
            return null;
        } catch (\Exception $e) {
            Log::error("Asaas error creating customer {$customer->id}: " . $e->getMessage(), [
                'code' => $e->getCode(),
            ]);
            return null;
        }
    }

    /**
     * Procurar cliente no Asaas por CPF/CNPJ
     *
     * @param string $cpfCnpj
     * @return array|null
     */
    private function findCustomerByCpf(string $cpfCnpj): ?array
    {
        try {
            $cleanDocument = $this->cleanDocument($cpfCnpj);

            $response = Http::withHeaders($this->getHeaders())
                ->timeout(config('asaas.timeout', 30))
                ->connectTimeout(config('asaas.connect_timeout', 10))
                ->get("{$this->baseUrl}/customers", [
                    'cpfCnpj' => $cleanDocument,
                    'limit' => 1,
                ]);

            if ($response->failed()) {
                Log::warning("Asaas error searching customer by CPF {$cleanDocument}", $response->json() ?? []);
                return null;
            }

            $data = $response->json();
            $customers = $data['data'] ?? [];

            if (!empty($customers)) {
                Log::debug("Asaas customer found by CPF {$cleanDocument}: {$customers[0]['id']}");
                return $customers[0];
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Asaas error finding customer by CPF: " . $e->getMessage());
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

            $response = Http::withHeaders($this->getHeaders())
                ->timeout(config('asaas.timeout', 30))
                ->connectTimeout(config('asaas.connect_timeout', 10))
                ->put("{$this->baseUrl}/customers/{$customer->asaas_customer_id}", $payload);

            if ($response->failed()) {
                Log::error("Asaas error updating customer {$customer->asaas_customer_id}: " . $response->json('message'), $response->json() ?? []);
                return null;
            }

            $data = $response->json();
            Log::info("Asaas customer updated: {$customer->asaas_customer_id}");
            return $data;
        } catch (\Exception $e) {
            Log::error("Asaas error updating customer {$customer->asaas_customer_id}: " . $e->getMessage(), [
                'code' => $e->getCode(),
            ]);
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

            Log::info("Criando cobrança no Asaas para fatura {$invoice->id}", [
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'amount' => $invoice->amount,
                'reference' => $invoice->reference,
            ]);

            $response = Http::withHeaders($this->getHeaders())
                ->timeout(config('asaas.timeout', 30))
                ->connectTimeout(config('asaas.connect_timeout', 10))
                ->post("{$this->baseUrl}/payments", $payload);

            if ($response->failed()) {
                $error = $response->json('message') ?? 'Unknown error';
                Log::error("Asaas error creating charge for invoice {$invoice->id}: {$error}", $response->json() ?? []);
                return null;
            }

            $data = $response->json();

            Log::info("Resposta completa do Asaas ao criar cobrança", [
                'invoice_id' => $invoice->id,
                'asaas_response' => $data,
            ]);

            if (isset($data['id'])) {
                // Extrair link e QR code da resposta
                $invoiceLink = $data['invoiceUrl'] ?? null;
                $pixQrCode = $data['pixQrCode']['qrCode'] ?? null;
                $pixUrl = $data['pixQrCode']['url'] ?? null;

                Log::info("Links extraídos do Asaas", [
                    'invoice_id' => $invoice->id,
                    'asaas_id' => $data['id'],
                    'invoice_link' => $invoiceLink,
                    'pix_qr_code_exists' => !empty($pixQrCode),
                    'pix_url' => $pixUrl,
                ]);

                $invoice->update([
                    'asaas_invoice_id' => $data['id'],
                    'asaas_sync_status' => 'pending',
                    'invoice_link' => $invoiceLink,
                    'invoice_qrcode' => $pixQrCode, // Salvar QR code PIX
                ]);

                Log::info("Cobrança criada com sucesso no Asaas", [
                    'invoice_id' => $invoice->id,
                    'asaas_invoice_id' => $data['id'],
                    'invoice_link_saved' => !empty($invoiceLink),
                    'qrcode_saved' => !empty($pixQrCode),
                ]);

                return $data;
            }

            Log::warning("Resposta do Asaas sem ID de cobrança", [
                'invoice_id' => $invoice->id,
                'response' => $data,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error("Asaas error creating charge for invoice {$invoice->id}: " . $e->getMessage(), [
                'code' => $e->getCode(),
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
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

            $response = Http::withHeaders($this->getHeaders())
                ->timeout(config('asaas.timeout', 30))
                ->connectTimeout(config('asaas.connect_timeout', 10))
                ->get("{$this->baseUrl}/payments/{$invoice->asaas_invoice_id}");

            if ($response->failed()) {
                Log::error("Asaas error fetching payment {$invoice->asaas_invoice_id}: " . $response->json('message'), $response->json() ?? []);
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
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

            $response = Http::withHeaders($this->getHeaders())
                ->timeout(config('asaas.timeout', 30))
                ->connectTimeout(config('asaas.connect_timeout', 10))
                ->get("{$this->baseUrl}/payments", $query);

            if ($response->failed()) {
                Log::error("Asaas error listing payments: " . $response->json('message'), $response->json() ?? []);
                return null;
            }

            $data = $response->json();
            return $data['data'] ?? [];
        } catch (\Exception $e) {
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

            $response = Http::withHeaders($this->getHeaders())
                ->timeout(config('asaas.timeout', 30))
                ->connectTimeout(config('asaas.connect_timeout', 10))
                ->delete("{$this->baseUrl}/payments/{$invoice->asaas_invoice_id}");

            if ($response->failed()) {
                Log::error("Asaas error canceling charge {$invoice->asaas_invoice_id}: " . $response->json('message'), $response->json() ?? []);
                return false;
            }

            $data = $response->json();

            if ($data['deleted'] ?? false) {
                $invoice->update([
                    'status' => 'canceled',
                    'asaas_sync_status' => 'canceled',
                ]);

                Log::info("Asaas charge canceled for invoice {$invoice->id}");
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error("Asaas error canceling charge {$invoice->asaas_invoice_id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obter URL de visualização da fatura no Asaas
     *
     * @param Invoice $invoice
     * @return string|null
     */
    public function getInvoiceViewUrl(Invoice $invoice): ?string
    {
        if (!$invoice->asaas_invoice_id) {
            return null;
        }

        $environment = config('asaas.environment', 'sandbox');
        $baseUrl = $environment === 'production'
            ? 'https://app.asaas.com/i'
            : 'https://sandbox.asaas.com/i';

        //codigo errado = pay_fm16jh9v2xo6kjz7, codigo certo fm16jh9v2xo6kjz7
        $invoiceId = preg_replace('/^pay_/', '', $invoice->asaas_invoice_id);
        return "{$baseUrl}/{$invoiceId}";
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
        $payload = [
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone ?? '',
            'cpfCnpj' => $this->cleanDocument($customer->document ?? ''),
            'externalReference' => (string) $customer->id,
        ];

        // Adicionar endereço apenas se disponível
        if ($customer->address) {
            $payload['addressStreet'] = $customer->address;
            $payload['addressNumber'] = $customer->address_number ?? '0';
            if ($customer->address_complement) {
                $payload['addressComplement'] = $customer->address_complement;
            }
            if ($customer->city) {
                $payload['addressCity'] = $customer->city->name ?? '';
                $payload['addressState'] = $customer->city->state ?? '';
            }
            if ($customer->postal_code) {
                $payload['addressPostalCode'] = $this->cleanPostalCode($customer->postal_code);
            }
        }

        // Log do payload para debug
        Log::debug("Asaas customer payload for customer {$customer->id}", $payload ?? []);

        return $payload;
    }

    /**
     * Construir payload de cobrança para Asaas
     *
     * @param Invoice $invoice
     * @return array
     */
    private function buildChargePayload(Invoice $invoice): array
    {
        // Usar o valor original se existir desconto, caso contrário usar o valor atual
        $chargeValue = $invoice->original_amount ?? $invoice->balance ?? $invoice->amount;

        // Definir tipo de cobrança - usar PIX para gerar QR code dinâmico
        $billingType = config('asaas.invoice.billing_type', 'PIX');

        $payload = [
            'customer' => $invoice->customer->asaas_customer_id,
            'description' => $invoice->notes ?? 'Mensalidade',
            'value' => $chargeValue,
            'dueDate' => $invoice->due_date->format('Y-m-d'),
            'billingType' => $billingType,
            'externalReference' => (string) $invoice->id,
        ];

        // Se existe desconto, adicionar ao payload
        if ($invoice->discount_value > 0) {
            $discountType = strtoupper($invoice->discount_type ?? 'PERCENTAGE');

            $payload['discount'] = [
                'value' => (float) $invoice->discount_value,
                'type' => $discountType, // PERCENTAGE ou FIXED
                'dueDateLimitDays' => 0, // 0 = até a data de vencimento
            ];
        }

        // Adicionar PIX dinâmico se configurado
        if (config('asaas.invoice.generate_pix', true)) {
            $payload['pixGenerationType'] = 'DYNAMIC';
        }

        // Notificar cliente se configurado
        if (config('asaas.invoice.notify_customer', true)) {
            $payload['notifyCustomer'] = true;
        }

        // Log do payload para debug
        Log::debug("Asaas charge payload for invoice {$invoice->id}", $payload ?? []);

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
            'Content-Type' => 'application/json',
            'User-Agent' => config('app.name') . '/1.0',
            'access_token' => $this->apiKey,
        ];
    }
}
