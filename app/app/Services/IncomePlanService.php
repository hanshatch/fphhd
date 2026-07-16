<?php

namespace App\Services;

use App\Models\IncomePlan;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class IncomePlanService
{
    /**
     * Ingresos esperados en los próximos N días.
     */
    public function upcoming(int $days = 30): Collection
    {
        return IncomePlan::upcoming($days)->with('account', 'source', 'category')->get();
    }

    /**
     * Resumen del mes: total esperado vs total ya registrado.
     */
    public function monthlySummary(?Carbon $month = null): array
    {
        $month ??= now();
        $from = $month->copy()->startOfMonth()->toDateString();
        $to   = $month->copy()->endOfMonth()->toDateString();

        $plans = IncomePlan::active()->with('source')->get();

        // Total esperado: planes que tienen next_expected_date en este mes
        $expected = bcsum(
            $plans->filter(fn ($p) => $p->next_expected_date->between($from, $to))
                ->pluck('expected_amount')
        );

        // Total ya registrado: solo ingreso operativo (regla 4.3, sin intereses)
        $registered = (string) (Transaction::where('type', 'income')
            ->whereBetween('date', [$from, $to])
            ->sum('amount') ?: 0);
        $registered = bcadd($registered, '0', 2);

        $pending = bcsub($expected, $registered, 2);
        if (bccomp($pending, '0', 2) < 0) {
            $pending = '0.00';
        }

        return [
            'expected'   => $expected,
            'registered' => $registered,
            'pending'    => $pending,
        ];
    }

    /**
     * Registra el ingreso real y avanza la fecha del plan.
     */
    public function register(IncomePlan $plan, string $amount, string $date, ?string $description = null): Transaction
    {
        $transaction = Transaction::create([
            'date'        => $date,
            'type'        => 'income',
            'amount'      => parse_money($amount),
            'account_id'  => $plan->account_id,
            'source_id'   => $plan->source_id,
            'category_id' => $plan->category_id,
            'description' => $description ?: $plan->name,
        ]);

        // Avanzar o desactivar según frecuencia
        $nextDate = $plan->calculateNextDate(Carbon::parse($date));

        if ($nextDate === null) {
            // Pago único: marcar como completado
            $plan->update(['is_active' => false]);
        } else {
            $plan->update(['next_expected_date' => $nextDate->toDateString()]);
        }

        return $transaction;
    }
}
