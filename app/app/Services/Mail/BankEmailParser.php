<?php

namespace App\Services\Mail;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Interpreta correos de notificación bancaria y devuelve un movimiento
 * normalizado, o null si el formato no se reconoce.
 *
 * Resultado:
 * [
 *   'bank'        => 'banamex' | 'revolut' | ...,
 *   'kind'        => 'expense' | 'income' | 'transfer_out',
 *   'amount'      => '20000.00',
 *   'currency'    => 'MXN' | 'BRL' | ...,
 *   'date'        => 'Y-m-d',
 *   'last4'       => '379' | null,
 *   'auth'        => '383450' | null,
 *   'description' => 'Retiro/Compra Banamex',
 *   'generic'     => bool   // true si la descripción no identifica al comercio
 * ]
 *
 * Para agregar un banco: sumar su remitente a SENDERS y un método parseXxx.
 */
class BankEmailParser
{
    /** Remitente (o dominio) → banco */
    public const SENDERS = [
        'revolut.com'      => 'revolut',
        'banamex.com'      => 'banamex',
        'citibanamex.com'  => 'banamex',
        'americanexpress.com' => 'amex',
        'aexp.com'         => 'amex',
        'nu.com.mx'        => 'nu',
        'nubank.com.br'    => 'nu',
        'mercadopago.com'  => 'mercadopago',
        'mercadopago.com.mx' => 'mercadopago',
        'openbank.mx'      => 'openbank',
    ];

    private const MONTHS = [
        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6,
        'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10,
        'noviembre' => 11, 'diciembre' => 12,
        'ene' => 1, 'feb' => 2, 'mar' => 3, 'abr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'ago' => 8, 'sep' => 9, 'sept' => 9, 'oct' => 10, 'nov' => 11, 'dic' => 12,
    ];

    /** Banco según el remitente, o null si no es un banco conocido */
    public function bankFor(string $from): ?string
    {
        $from = mb_strtolower(trim($from));

        foreach (self::SENDERS as $needle => $bank) {
            if (str_ends_with($from, '@' . $needle) || str_ends_with($from, '.' . $needle)) {
                return $bank;
            }
        }

        return null;
    }

    public function parse(string $from, string $subject, string $text, Carbon $receivedAt): ?array
    {
        $bank = $this->bankFor($from);

        if ($bank === null) {
            return null;
        }

        $result = match ($bank) {
            'banamex' => $this->parseBanamex($subject, $text, $receivedAt),
            'revolut' => $this->parseRevolut($subject, $text, $receivedAt),
            'nu'      => $this->parseNu($subject, $text, $receivedAt),
            'openbank' => $this->parseOpenbank($subject, $text, $receivedAt),
            default   => null,
        };

        return $result ? array_merge(['bank' => $bank], $result) : null;
    }

    // ── Banamex ───────────────────────────────────────────────────────

    /**
     * Asuntos: "Retiro/Compra con cuenta Banamex", "Depósito a cuenta Banamex".
     * Cuerpo: "Monto $ 20,000.00 M.N.", "Fecha y hora 30 Septiembre 2026 / 14:56:00",
     *         "Cheques M.N. ***379", "No. Autorización 383450".
     */
    private function parseBanamex(string $subject, string $text, Carbon $receivedAt): ?array
    {
        $s = Str::ascii(mb_strtolower($subject));

        // Aviso de alta de domiciliación: no es un cargo, se ignora sin avisar
        if (str_contains($s, 'autorizacion de cargo')) {
            return ['kind' => 'info', 'description' => $subject];
        }

        $kind = match (true) {
            str_contains($s, 'deposito')                            => 'income',
            str_contains($s, 'retiro') || str_contains($s, 'compra') => 'expense',
            str_contains($s, 'cargo a cuenta')                      => 'expense',
            str_contains($s, 'transferencia') && str_contains($s, 'enviada') => 'expense',
            default => null,
        };

        if ($kind === null) {
            return null;
        }

        if (! preg_match('/Monto\s*\$?\s*([\d.,]+)/iu', $text, $m)) {
            return null;
        }

        $amount = parse_money($m[1]);

        if ($amount === null || bccomp($amount, '0.00', 2) <= 0) {
            return null;
        }

        // "30 Septiembre 2026 / 14:56:00" (cuenta), "2026/09/26 11:44:43 AM" (tarjeta), "29/09/26 19:26:18" (domiciliado)
        $date = match (true) {
            (bool) preg_match('/Fecha y hora\s*(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})/iu', $text, $d) => $this->spanishDate((int) $d[1], $d[2], (int) $d[3]),
            (bool) preg_match('/Fecha y hora\s*(\d{4})\/(\d{2})\/(\d{2})/iu', $text, $d)             => checkdate((int) $d[2], (int) $d[3], (int) $d[1]) ? "{$d[1]}-{$d[2]}-{$d[3]}" : null,
            (bool) preg_match('/Fecha y hora\s*(\d{2})\/(\d{2})\/(\d{2})\b/iu', $text, $d)          => checkdate((int) $d[2], (int) $d[1], 2000 + (int) $d[3]) ? sprintf('20%s-%s-%s', $d[3], $d[2], $d[1]) : null,
            default => null,
        };

        // "***379", "**117" o "Cuenta de cargo 894"
        if (! preg_match('/\*{2,}\s*(\d{3,4})/u', $text, $l)) {
            preg_match('/Cuenta de cargo:?\s*\n?\s*(\d{3,4})\b/iu', $text, $l);
        }
        preg_match('/No\.?\s*Autorizaci[oó]n\s*:?\s*([A-Z0-9][A-Z0-9 ]{2,40})/iu', $text, $a);
        $auth = isset($a[1]) ? trim(preg_replace('/\s+(Protege|Informaci|Estatus).*$/is', '', $a[1])) : null;

        // Compras con tarjeta traen el comercio: "Establecimiento\nECOMMERCE SAN PABLO MEX"
        $merchant = preg_match('/(?:^|\n)Establecimiento:?\s*\n?\s*([^\n]+)/iu', $text, $e) ? trim($e[1]) : null;
        if ($merchant !== null && preg_match('/^(monto|fecha|estatus)/iu', $merchant)) {
            $merchant = null;
        }

        $operation = preg_match('/siguiente operaci[oó]n:\s*([^\n]+)/iu', $text, $o) ? trim(preg_replace('/\s*\/\s*/', '/', $o[1])) : null;

        return [
            'kind'        => $kind,
            'amount'      => $amount,
            'currency'    => 'MXN',
            'date'        => $date ?? $receivedAt->toDateString(),
            'last4'       => $l[1] ?? null,
            'auth'        => $auth ?: null,
            'description' => $merchant
                ? Str::title(mb_strtolower($merchant))
                : ($operation ?: ($kind === 'income' ? 'Depósito' : 'Retiro/Compra')) . ' Banamex',
            'generic'     => $merchant === null,
        ];
    }

    // ── Revolut ───────────────────────────────────────────────────────

    /**
     * Asuntos: "Enviaste 5.800 BRL a Samanta Motta Hatch", "Recibiste 700 MXN de ...",
     *          "Pagaste 64,50 MXN en OXXO".
     * Cuerpo: "El miércoles, 30 septiembre, 15:08".
     */
    private function parseRevolut(string $subject, string $text, Carbon $receivedAt): ?array
    {
        $subject = trim(preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '', $subject));

        if (! preg_match('/^(Enviaste|Recibiste|Pagaste|Retiraste)\s+([\d.,]+)\s*([A-Z]{3})\s+(?:a|de|en)\s+(.+)$/iu', $subject, $m)) {
            return null;
        }

        $verb   = Str::ascii(mb_strtolower($m[1]));
        $amount = $this->revolutAmount($m[2]);
        $who    = trim($m[4]);

        if ($amount === null) {
            return null;
        }

        [$kind, $description] = match ($verb) {
            'enviaste'  => ['transfer_out', 'Transferencia a ' . $who],
            'recibiste' => ['income', 'Depósito de ' . $who],
            default     => ['expense', $who],
        };

        $date = preg_match('/(?:^|\n)El\s+(?:[[:alpha:]]+,\s*)?(\d{1,2})\s+([[:alpha:]]+)(?:,|\s+de)?\s*(\d{4})?/iu', $text, $d)
            ? $this->spanishDate((int) $d[1], $d[2], isset($d[3]) && $d[3] !== '' ? (int) $d[3] : (int) $receivedAt->year)
            : null;

        return [
            'counterparty' => in_array($verb, ['enviaste', 'recibiste'], true) ? $who : null,
            'kind'        => $kind,
            'amount'      => $amount,
            'currency'    => strtoupper($m[3]),
            'date'        => $date ?? $receivedAt->toDateString(),
            'last4'       => null,
            'auth'        => null,
            'description' => Str::limit($description, 500, ''),
            'generic'     => $verb === 'enviaste' || $verb === 'recibiste',
        ];
    }

    // ── Nu ────────────────────────────────────────────────────────────

    /**
     * Asuntos:
     *  - "¡Recibiste una transferencia!": "<NOMBRE> hizo una transferencia a tu Cuenta Nu por: Monto: $300.00 / Fecha: 19 DIC 2025"
     *  - "Tu transferencia fue exitosa":   "la transferencia que hiciste a la cuenta de <NOMBRE> en <BANCO> fue exitosa. Monto: $850.00 / Fecha: 09/OCT/2025"
     *  - "Tu comprobante de pago de recarga de celular": "Monto: $200.00 … Empresa…: Telcel Amigo Sin Límite … Código de operación: <uuid>"
     * Correos de Nu sin monto (avisos, contactos, códigos) son informativos.
     */
    private function parseNu(string $subject, string $text, Carbon $receivedAt): ?array
    {
        if (! preg_match('/Monto:?\s*\$?\s*([\d.,]+)/iu', $text, $m)) {
            return ['kind' => 'info', 'description' => $subject];
        }

        $amount = parse_money($m[1]);

        if ($amount === null || bccomp($amount, '0.00', 2) <= 0) {
            return ['kind' => 'info', 'description' => $subject];
        }

        $date = $this->nuDate($text) ?? $receivedAt->toDateString();
        $base = ['amount' => $amount, 'currency' => 'MXN', 'date' => $date, 'last4' => null, 'auth' => null];

        if (preg_match('/\n?([^\n]+?)\s+hizo una transferencia a tu Cuenta\s+Nu/iu', $text, $w)) {
            $who = trim($w[1]);

            return $base + [
                'kind'         => 'income',
                'counterparty' => $who,
                'description'  => 'Transferencia de ' . Str::title(mb_strtolower(str_replace([',', '/'], ' ', $who))),
                'generic'      => true,
            ];
        }

        if (preg_match('/transferencia que hiciste a la cuenta de\s+(.+?)\s+en\s+(.+?)\s+fue exitosa/iu', $text, $w)) {
            return $base + [
                'kind'         => 'transfer_out',
                'counterparty' => trim($w[1]) . ' ' . trim($w[2]),
                'description'  => 'Transferencia a ' . trim($w[1]) . ' (' . trim($w[2]) . ')',
                'generic'      => true,
            ];
        }

        if (preg_match('/Empresa a la cual se realizar[aá] el pago:\s*([^\n]+)/iu', $text, $c)) {
            $phone = preg_match('/N[uú]mero de celular:\s*(\d{6,})/iu', $text, $ph) ? $ph[1] : null;
            $op    = preg_match('/C[oó]digo de operaci[oó]n:\s*([A-Za-z0-9-]{8,40})/iu', $text, $o) ? $o[1] : null;

            return array_merge($base, [
                'kind'        => 'expense',
                'auth'        => $op,
                'description' => trim($c[1]) . ($phone ? ' · ' . $phone : ''),
                'generic'     => false,
            ]);
        }

        return null;
    }

    // ── OpenBank ──────────────────────────────────────────────────────

    /**
     * - "Recarga exitosa": "Tu recarga de tiempo aire al teléfono 5543589391 por $200.00 se confirmó"
     * - "Abono exitoso":   "Abonaste $ 24,400.00 desde tu cuenta ****9617 a tus Apartados Open el 24/07/2026"
     *   (movimiento interno: de la cuenta a los Apartados)
     * Códigos, límites, tarjeta y estado de cuenta son informativos.
     */
    private function parseOpenbank(string $subject, string $text, Carbon $receivedAt): ?array
    {
        if (preg_match('/recarga de tiempo aire al tel[eé]fono\s+(\d{6,})\s+por\s+\$\s*([\d.,]+)/iu', $text, $m)) {
            $amount = parse_money($m[2]);

            return $amount === null ? null : [
                'kind'        => 'expense',
                'amount'      => $amount,
                'currency'    => 'MXN',
                'date'        => $receivedAt->toDateString(),
                'last4'       => null,
                'auth'        => null,
                'description' => 'Recarga tiempo aire · ' . $m[1],
                'generic'     => false,
            ];
        }

        if (preg_match('/Abonaste\s+\$\s*([\d.,]+)\s+desde tu cuenta\s+\*+\s*(\d{3,4})\s+a tus Apartados[^\n]*?el\s+(\d{1,2})\/(\d{1,2})\/(\d{4})/iu', $text, $m)) {
            $amount = parse_money($m[1]);
            $date   = checkdate((int) $m[4], (int) $m[3], (int) $m[5]) ? sprintf('%04d-%02d-%02d', $m[5], $m[4], $m[3]) : $receivedAt->toDateString();

            return $amount === null ? null : [
                'kind'        => 'to_savings',
                'amount'      => $amount,
                'currency'    => 'MXN',
                'date'        => $date,
                'last4'       => $m[2],
                'auth'        => null,
                'description' => 'Abono a Apartados Open',
                'generic'     => true,
            ];
        }

        // Sin monto: aviso informativo (códigos, límites, tarjeta, estado de cuenta)
        if (! preg_match('/\$\s*[\d.,]+/', $text)) {
            return ['kind' => 'info', 'description' => $subject];
        }

        return null;
    }

    /** "30 SEP 2026", "09/OCT/2025", "07/02/2026" o "6 oct 2025 - 06:19:48" */
    private function nuDate(string $text): ?string
    {
        return match (true) {
            (bool) preg_match('/Fecha:\s*(\d{1,2})\s+([[:alpha:]]{3,})\.?\s+(\d{4})/iu', $text, $d)      => $this->spanishDate((int) $d[1], $d[2], (int) $d[3]),
            (bool) preg_match('/Fecha:\s*(\d{1,2})\/([[:alpha:]]{3,})\/(\d{4})/iu', $text, $d)           => $this->spanishDate((int) $d[1], $d[2], (int) $d[3]),
            (bool) preg_match('/Fecha:\s*(\d{1,2})\/(\d{1,2})\/(\d{4})/u', $text, $d)                  => checkdate((int) $d[2], (int) $d[1], (int) $d[3]) ? sprintf('%04d-%02d-%02d', $d[3], $d[2], $d[1]) : null,
            (bool) preg_match('/(\d{1,2})\s+([[:alpha:]]{3,})\.?\s+(\d{4})\s*-\s*\d{1,2}:\d{2}/iu', $text, $d) => $this->spanishDate((int) $d[1], $d[2], (int) $d[3]),
            default => null,
        };
    }

    /** Revolut usa "5.800" (miles) y "64,50" (decimales) al estilo europeo */
    private function revolutAmount(string $raw): ?string
    {
        if (preg_match('/^\d{1,3}(\.\d{3})*,\d{1,2}$/', $raw)) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } elseif (preg_match('/^\d+,\d{1,2}$/', $raw)) {
            $raw = str_replace(',', '.', $raw);
        }

        return parse_money($raw);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function spanishDate(int $day, string $month, int $year): ?string
    {
        $m = self::MONTHS[Str::ascii(mb_strtolower(rtrim($month, '.')))] ?? null;

        if ($m === null || ! checkdate($m, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $m, $day);
    }
}
