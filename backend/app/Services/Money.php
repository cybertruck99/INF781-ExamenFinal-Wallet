<?php

namespace App\Services;

final class Money
{
    public static function toCents(string|float|int $amount): int
    {
        $value = is_string($amount) ? str_replace(',', '.', $amount) : (string) $amount;
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            abort(422, 'Monto inválido.');
        }

        [$whole, $decimal] = array_pad(explode('.', $value, 2), 2, '0');
        $decimal = str_pad(substr($decimal, 0, 2), 2, '0');

        return ((int) $whole * 100) + (int) $decimal;
    }

    public static function toDecimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
