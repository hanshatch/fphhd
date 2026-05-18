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
        $expected = $plans->filter(fn ($p) =>
            $p->next_expected_date->between($from, $to)
        )->sum(fn ($p) => (float) $p->expected_amount);

        // Total ya registrado: transacciones de ingreso del mes
        $registered = Transaction::whereIn('type', ['income', 'interest'])
            ->whereBetween('date', [$from, $to])
            ->sum('amount');

        return [
            'expected'   => number_format($expected, 2, '.', ''),
            'registered' => number_format((float) $registered, 2, '.', ''),
            'pending'    => number_format(max(0, $expected - (float) $registered), 2, '.', ''),
        ];
    }

    /**
     * Registra el ingreso real y avanza la fecha del plan.
     */
    public function register(IncomePlan $plan, float $amount, string $date, ?string $description = null): Transaction
    {
        $transaction = Transaction::create([
            'date'        => $date,
            'type'        => 'income',
            'amount'      => number_format($amount, 2, '.', ''),
            'account_id'  => $plan->account_id,
            'source_id'   => $plan->source_id,
            'category_id' => $plan->category_id,
            'description' => $description ?: $plan->name,
        ]);

        // Avanzar siguiente fecha esperada
        $nextDate = $plan->calculateNextDate(Carbon::parse($date));
        $plan->update(['next_expected_date' => $nextDate->toDateString()]);

        return $transaction;
    }
}
