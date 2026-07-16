<?php

if (! function_exists('parse_money')) {
    /**
     * Normaliza un monto capturado por el usuario a string decimal con 2 posiciones.
     * Acepta "1,234.56", "1234.56", ".50", " 1 234.56 ". Nunca usa float.
     * Devuelve null si el valor está vacío o no es un número válido.
     */
    function parse_money(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = str_replace([',', ' ', '$'], '', trim((string) $value));

        if ($clean === '' || ! is_numeric($clean)) {
            return null;
        }

        // bcadd normaliza a 2 decimales sin pasar por float
        return bcadd($clean, '0', 2);
    }
}

if (! function_exists('format_currency')) {
    /**
     * Formatea un monto para mostrar: $1,234.56 (solo presentación).
     */
    function format_currency(mixed $amount, bool $withSymbol = true): string
    {
        $normalized = parse_money($amount) ?? '0.00';

        [$int, $dec] = explode('.', $normalized);

        $sign = str_starts_with($int, '-') ? '-' : '';
        $int  = ltrim($int, '-');

        $grouped = strrev(implode(',', str_split(strrev($int), 3)));

        return $sign . ($withSymbol ? '$' : '') . $grouped . '.' . $dec;
    }
}

if (! function_exists('bcsum')) {
    /**
     * Suma una lista de montos (strings) con bcmath, escala 2.
     */
    function bcsum(iterable $amounts): string
    {
        $total = '0.00';

        foreach ($amounts as $amount) {
            $total = bcadd($total, (string) ($amount ?: 0), 2);
        }

        return $total;
    }
}
