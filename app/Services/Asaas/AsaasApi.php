<?php

namespace App\Services\Asaas;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Chamadas à API de cobranças do Asaas usadas pelas operações do sistema (ChargeOperations).
 *
 * Diferente do AsaasService (que engole os erros e devolve null), aqui toda recusa vira uma
 * ChargeOperationException com a descrição que o Asaas mandou em errors[].description, para o
 * usuário ver o motivo e o sistema não alterar nada.
 */
class AsaasApi
{
    private string $apiKey;

    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) (config('asaas.api_key') ?: '');
        $endpoints = config('asaas.endpoints', [
            'sandbox' => 'https://sandbox.asaas.com/api/v3',
            'production' => 'https://api.asaas.com/api/v3',
        ]);
        $this->baseUrl = $endpoints[config('asaas.environment', 'sandbox')] ?? 'https://sandbox.asaas.com/api/v3';
    }

    /** @return array<string, mixed> */
    public function createPayment(array $payload): array
    {
        return $this->send('post', 'payments', $payload);
    }

    /** @return array<string, mixed> */
    public function getPayment(string $id): array
    {
        return $this->send('get', "payments/{$id}");
    }

    /**
     * PUT /payments/{id}: billingType, value e dueDate são obrigatórios; o cliente não muda.
     *
     * @return array<string, mixed>
     */
    public function updatePayment(string $id, array $payload): array
    {
        return $this->send('put', "payments/{$id}", $payload);
    }

    /** @return array<string, mixed> {deleted: true, id} */
    public function deletePayment(string $id): array
    {
        return $this->send('delete', "payments/{$id}");
    }

    /** @return array<string, mixed> */
    public function restorePayment(string $id): array
    {
        return $this->send('post', "payments/{$id}/restore");
    }

    /**
     * Baixa manual (recebimento fora do Asaas): a cobrança fica RECEIVED_IN_CASH.
     *
     * @return array<string, mixed>
     */
    public function receiveInCash(string $id, string $paymentDate, float $value, bool $notifyCustomer = false): array
    {
        return $this->send('post', "payments/{$id}/receiveInCash", [
            'paymentDate' => $paymentDate,
            'value' => round($value, 2),
            'notifyCustomer' => $notifyCustomer,
        ]);
    }

    /** @return array<string, mixed> */
    public function undoReceivedInCash(string $id): array
    {
        return $this->send('post', "payments/{$id}/undoReceivedInCash");
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ChargeOperationException
     */
    private function send(string $method, string $path, array $payload = []): array
    {
        $request = Http::withHeaders([
            'Content-Type' => 'application/json',
            'User-Agent' => config('app.name').'/1.0',
            'access_token' => $this->apiKey,
        ])
            ->timeout((int) config('asaas.timeout', 30))
            ->connectTimeout((int) config('asaas.connect_timeout', 10))
            ->acceptJson();

        $url = "{$this->baseUrl}/{$path}";

        try {
            /** @var Response $response */
            $response = match ($method) {
                'get' => $request->get($url),
                'delete' => $request->delete($url),
                'put' => $request->put($url, $payload),
                // restore e undoReceivedInCash não têm corpo
                default => $payload === [] ? $request->send('POST', $url) : $request->post($url, $payload),
            };
        } catch (ConnectionException $e) {
            Log::error("Asaas: falha de conexão em {$method} {$path}", ['error' => $e->getMessage()]);

            throw new ChargeOperationException('Não foi possível falar com o Asaas agora (falha de conexão). Nada foi alterado; tente de novo em instantes.', previous: $e);
        }

        if ($response->failed()) {
            $error = self::errorMessage($response);
            Log::warning("Asaas recusou {$method} {$path}: {$error}", ['response' => $response->json()]);

            throw ChargeOperationException::refused($error);
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw ChargeOperationException::refused('resposta inesperada da API.');
        }

        return $data;
    }

    /** "errors[].description" juntos; senão a mensagem ou o código HTTP. */
    public static function errorMessage(Response $response): string
    {
        $descriptions = collect($response->json('errors') ?? [])
            ->map(fn ($error): ?string => is_array($error) ? ($error['description'] ?? $error['detail'] ?? null) : null)
            ->filter()
            ->implode(' ');

        return $descriptions !== ''
            ? $descriptions
            : ($response->json('message') ?? "erro HTTP {$response->status()}.");
    }
}
