<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CNPJ com dígitos verificadores válidos. Aceita o formato alfanumérico (letras nas 12 primeiras
 * posições, emitido pela Receita desde julho de 2026): cada caractere vale o código ASCII menos 48.
 */
class Cnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid((string) $value)) {
            $fail('Informe um CNPJ válido.');
        }
    }

    public static function isValid(string $value): bool
    {
        $cnpj = strtoupper(preg_replace('/[.\/\-\s]/', '', $value));

        if (! preg_match('/^[0-9A-Z]{12}[0-9]{2}$/', $cnpj) || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $values = array_map(fn (string $char): int => ord($char) - 48, str_split($cnpj));

        foreach ([12, 13] as $length) {
            $weights = array_slice([6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], 13 - $length);
            $sum = 0;

            foreach ($weights as $index => $weight) {
                $sum += $values[$index] * $weight;
            }

            $digit = $sum % 11 < 2 ? 0 : 11 - $sum % 11;

            if ($values[$length] !== $digit) {
                return false;
            }
        }

        return true;
    }
}
