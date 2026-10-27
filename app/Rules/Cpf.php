<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** CPF com dígitos verificadores válidos, com ou sem máscara. */
class Cpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid((string) $value)) {
            $fail('Informe um CPF válido.');
        }
    }

    public static function isValid(string $value): bool
    {
        $cpf = self::digits($value);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        foreach ([9, 10] as $length) {
            $sum = 0;

            for ($index = 0; $index < $length; $index++) {
                $sum += (int) $cpf[$index] * ($length + 1 - $index);
            }

            $digit = ($sum * 10) % 11 % 10;

            if ((int) $cpf[$length] !== $digit) {
                return false;
            }
        }

        return true;
    }

    public static function digits(string $value): string
    {
        return preg_replace('/\D/', '', $value);
    }

    /** "000.000.000-00": o formato gravado no cadastro de clientes. */
    public static function format(string $value): string
    {
        $cpf = self::digits($value);

        return strlen($cpf) === 11
            ? substr($cpf, 0, 3).'.'.substr($cpf, 3, 3).'.'.substr($cpf, 6, 3).'-'.substr($cpf, 9, 2)
            : $value;
    }
}
