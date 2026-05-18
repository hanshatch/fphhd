<?php

namespace App\Http\Controllers;

use App\Models\IncomePlan;
use App\Models\RecurringCharge;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ScheduledController extends Controller
{
    public function index(Request $request): View
    {
        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->month)->startOfMonth()
            : now()->startOfMonth();

        $from = $month->toDateString();
        $to   = $month->copy()->endOfMonth()->toDateString();

        // Cargos recurrentes con next_application_date en el mes
        $charges = RecurringCharge::active()
            ->whereBetween('next_application_date', [$from, $to])
            ->with('account', 'category')
            ->orderBy('next_application_date')
            ->get();

        // Ingresos planeados con next_expected_date en el mes
        $incomes = IncomePlan::active()
            ->whereBetween('next_expected_date', [$from, $to])
            ->with('account', 'source')
            ->orderBy('next_expected_date')
            ->get();

        // Construir mapa día → items para el calendario
        $dayMap = [];

        foreach ($charges as $charge) {
            $day = $charge->next_application_date->day;
            $dayMap[$day][] = [
                'type'   => 'charge',
                'label'  => $charge->name,
                'amount' => (float) $charge->amount,
                'color'  => '#ef4444',
                'account'=> $charge->account?->name,
                'category'=> $charge->category?->name,
                'date'   => $charge->next_application_date,
            ];
        }

        foreach ($incomes as $income) {
            $day = $income->next_expected_date->day;
            $dayMap[$day][] = [
                'type'   => 'income',
                'label'  => $income->name,
                'amount' => (float) $income->expected_amount,
                'color'  => '#76a72b',
                'account'=> $income->account?->name,
                'source' => $income->source?->name,
                'date'   => $income->next_expected_date,
            ];
        }

        ksort($dayMap);

        // Totales del mes
        $totalIn  = $incomes->sum(fn ($i) => (float) $i->expected_amount);
        $totalOut = $charges->sum(fn ($c) => (float) $c->amount);

        return view('pages.scheduled.index', [
            'month'    => $month,
            'dayMap'   => $dayMap,
            'totalIn'  => $totalIn,
            'totalOut' => $totalOut,
            'prev'     => $month->copy()->subMonth()->format('Y-m'),
            'next'     => $month->copy()->addMonth()->format('Y-m'),
        ]);
    }
}
