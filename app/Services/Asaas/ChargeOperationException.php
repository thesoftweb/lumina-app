<?php

namespace App\Services\Asaas;

use DomainException;

/**
 * Operação de fatura recusada (pelo Asaas ou por uma regra do sistema). A mensagem é para o usuário
 * e, quando ela é lançada, nada foi alterado no sistema.
 */
class ChargeOperationException extends DomainException
{
    public static function refused(string $reason): self
    {
        return new self('O Asaas recusou a operação: '.rtrim($reason, '.').'. Nada foi alterado no sistema.');
    }

    public static function unexpected(string $what): self
    {
        return new self("O Asaas respondeu de forma inesperada ({$what}). Nada foi alterado no sistema; confira a cobrança no Asaas e tente de novo.");
    }
}
