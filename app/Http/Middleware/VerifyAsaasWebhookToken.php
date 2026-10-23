<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Só aceita chamadas do Asaas: o token cadastrado na configuração do webhook (painel do Asaas) chega
 * no cabeçalho "asaas-access-token" e tem de ser igual a ASAAS_WEBHOOK_SECRET. Sem segredo
 * configurado, rejeita tudo: o webhook nunca fica aberto.
 */
class VerifyAsaasWebhookToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('asaas.webhook.secret');

        if ($secret === '') {
            Log::error('Webhook do Asaas rejeitado: ASAAS_WEBHOOK_SECRET não está configurado no .env. Cadastre o mesmo token na configuração do webhook no painel do Asaas.', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Webhook não configurado'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $request->header('asaas-access-token');

        if (! is_string($token) || ! hash_equals($secret, $token)) {
            // Sem gravar o payload: pode ser uma tentativa de forjar notificações
            Log::warning('Webhook do Asaas rejeitado: token ausente ou inválido.', [
                'ip' => $request->ip(),
                'token_sent' => is_string($token) && $token !== '',
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json(['error' => 'Não autorizado'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
