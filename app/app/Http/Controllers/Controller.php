<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Normaliza campos de dinero del request ANTES de validar, para aceptar
     * "1,234.56", "1234.56" y ".50" (regla 4.1). Si el valor no es parseable
     * se deja intacto para que la validación `numeric` lo rechace con mensaje.
     */
    protected function normalizeMoney(Request $request, array $fields): void
    {
        $normalized = [];

        foreach ($fields as $field) {
            $raw = $request->input($field);

            if ($raw !== null && ($parsed = parse_money($raw)) !== null) {
                $normalized[$field] = $parsed;
            }
        }

        if ($normalized !== []) {
            $request->merge($normalized);
        }
    }
}
