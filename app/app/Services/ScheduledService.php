<?php

namespace App\Services;

use App\Models\IncomePlan;
use App\Models\RecurringCharge;
use Illuminate\Support\Carbon;

class ScheduledService
{
    /**
     * Calendario de flujo de caja del mes: mapa día → items,
     * totales con bcmath y navegación prev/next.
     */
    public function monthCalendar(Carbon $month): array
    {
        $from = $month->toDateString();
        $to   = $month->copy()->endOfMonth()->toDateString();

        $charges = RecurringCharge::active()
            ->whereBetween('next_application_date', [$from, $to])
            ->with('account', 'category')
            ->orderBy('next_application_date')
            ->get();

        $incomes = IncomePlan::active()
            ->whereBetween('next_expected_date', [$from, $to])
            ->with('account', 'source')
            ->orderBy('next_expected_date')
            ->get();

        $dayMap = [];

        foreach ($charges as $charge) {
            $dayMap[$charge->next_application_date->day][] = [
                'type'     => 'charge',
                'label'    => $charge->name,
                'amount'   => (string) $charge->amount,
                'color'    => '#ef4444',
                'account'  => $charge->account?->name,
                'category' => $charge->category?->name,
                'date'     => $charge->next_application_date,
            ];
        }

        foreach ($incomes as $income) {
            $dayMap[$income->next_expected_date->day][] = [
                'type'    => 'income',
                'label'   => $income->name,
                'amount'  => (string) $income->expected_amount,
                'color'   => '#76a72b',
                'account' => $income->account?->name,
                'source'  => $income->source?->name,
                'date'    => $income->next_expected_date,
            ];
        }

        ksort($dayMap);

        return [
            'month'    => $month,
            'dayMap'   => $dayMap,
            'totalIn'  => bcsum($incomes->pluck('expected_amount')),
            'totalOut' => bcsum($charges->pluck('amount')),
            'prev'     => $month->copy()->subMonth()->format('Y-m'),
            'next'     => $month->copy()->addMonth()->format('Y-m'),
        ];
    }
}
