<?php

namespace App\Services;

use App\Models\Account;
use App\Models\RecurringCharge;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Empata movimientos que llegan del banco (captura o correo) con cargos
 * recurrentes de la misma cuenta, tolerando que el monto varíe (cargos en
 * USD/UDIs). Dos resultados posibles por movimiento:
 *
 *  - apply:  el recurrente sigue pendiente → aplicarlo con el monto real
 *  - adjust: el recurrente ya se aplicó con el estimado → corregir ese movimiento
 *
 * Con varios candidatos (ej. tres seguros de la misma aseguradora) se
 * empareja cada movimiento con el recurrente de monto más cercano.
 */
class RecurringMatchService
{
    private const DAYS = 5;

    /**
     * $rows: [clave => ['type' => 'expense'|'income', 'amount' => '10519.54',
     *                   'date' => 'Y-m-d', 'description' => '...']]
     *
     * Devuelve [clave => ['mode' => 'apply'|'adjust', 'charge' => RecurringCharge,
     *                     'transaction' => ?Transaction]]
     */
    public function assign(Account $account, array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $charges = RecurringCharge::with('category')->where('account_id', $account->id)->get();

        if ($charges->isEmpty()) {
            return [];
        }

        $pairs = [];

        foreach ($rows as $key => $row) {
            $date = Carbon::parse($row['date']);

            foreach ($charges as $charge) {
                if ($charge->type !== $row['type']) {
                    continue;
                }

                // El banco casi nunca usa tu nombre del cargo ("APPLE.COM/BILL" vs "Servicio iCloud"):
                // un monto idéntico en la misma cuenta y fecha basta aunque el nombre no coincida
                $nameOk = $this->nameMatches($charge, $row['description']);
                $exact  = bccomp((string) $charge->amount, $row['amount'], 2) === 0;

                // Pendiente: aplicar con el monto real
                if ($charge->is_active
                    && ($nameOk || $exact)
                    && abs($charge->next_application_date->diffInDays($date, false)) <= self::DAYS
                    && ($score = $this->score($charge, (string) $charge->amount, $row['amount'])) !== null) {
                    $pairs[] = [$score, $key, 'c' . $charge->id, 'apply', $charge, null];
                }

                if (! $nameOk) {
                    continue;
                }

                // Ya aplicado con el estimado: ajustar ese movimiento
                $applied = Transaction::where('account_id', $account->id)
                    ->where('type', $charge->type)
                    ->where('description', 'like', $charge->name . '%')
                    ->whereDate('date', '>=', $date->copy()->subDays(self::DAYS)->toDateString())
                    ->whereDate('date', '<=', $date->copy()->addDays(self::DAYS)->toDateString())
                    ->get();

                foreach ($applied as $tx) {
                    if (bccomp((string) $tx->amount, $row['amount'], 2) === 0) {
                        continue; // idéntico: ya lo cubre la detección de duplicados
                    }

                    if (($score = $this->score($charge, (string) $tx->amount, $row['amount'])) !== null) {
                        $pairs[] = [$score, $key, 't' . $tx->id, 'adjust', $charge, $tx];
                    }
                }
            }
        }

        // Emparejamiento voraz por menor diferencia; cada movimiento y cada destino una sola vez
        usort($pairs, fn ($a, $b) => bccomp($a[0], $b[0], 6));

        $result = [];
        $used   = [];

        foreach ($pairs as [$score, $key, $target, $mode, $charge, $tx]) {
            if (isset($result[$key]) || isset($used[$target])) {
                continue;
            }

            // Un recurrente que se aplica no puede también ajustarse en la misma pasada
            if (isset($used['c' . $charge->id]) && $mode === 'apply') {
                continue;
            }

            $result[$key]  = ['mode' => $mode, 'charge' => $charge, 'transaction' => $tx];
            $used[$target] = true;
        }

        return $result;
    }

    /** Diferencia relativa en % (string bcmath) si cae en la tolerancia del recurrente; null si no */
    private function score(RecurringCharge $charge, string $expected, string $actual): ?string
    {
        if (bccomp($expected, '0', 2) <= 0) {
            return null;
        }

        $diff = bcsub($actual, $expected, 2);
        if (bccomp($diff, '0', 2) < 0) {
            $diff = bcmul($diff, '-1', 2);
        }

        $pct       = bcdiv(bcmul($diff, '100', 6), $expected, 6);
        $tolerance = (string) ($charge->amount_tolerance_pct ?? '0');

        return bccomp($pct, $tolerance, 6) <= 0 ? $pct : null;
    }

    /**
     * Con «texto en el estado de cuenta» definido, debe aparecer en la
     * descripción. Si no, basta una palabra significativa en común con el nombre.
     */
    private function nameMatches(RecurringCharge $charge, string $description): bool
    {
        $haystack = $this->normalize($description);

        if (filled($charge->statement_text)) {
            // También solo letras: "apple com bill" empata "APPLE.COM/BILL CUPERTINO"
            return str_contains($haystack, $this->normalize($charge->statement_text))
                || str_contains($this->letters($description), $this->letters($charge->statement_text));
        }

        $words = array_intersect($this->words($charge->name), $this->words($description));

        return $words !== [];
    }

    /** Palabras de 4+ letras, sin acentos, sin ruido bancario */
    private function words(string $text): array
    {
        $noise = ['domi', 'pago', 'cargo', 'retiro', 'compra', 'cuota', 'mensual', 'mensualidad', 'transferencia', 'banamex'];

        preg_match_all('/[a-z]{4,}/', $this->normalize($text), $m);

        return array_values(array_diff(array_unique($m[0]), $noise));
    }

    private function letters(string $text): string
    {
        return trim(preg_replace('/[^a-z]+/', ' ', Str::ascii(mb_strtolower($text))));
    }

    private function normalize(string $text): string
    {
        return preg_replace('/\s+/', ' ', Str::ascii(mb_strtolower(trim($text))));
    }
}
