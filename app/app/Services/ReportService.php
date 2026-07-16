<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Carbon;

class ReportService
{
    /**
     * Reporte anual: ingreso operativo vs egresos por mes, con acumulado.
     * Totales en bcmath; las series para Chart.js se castean a float
     * solo en el punto de serialización.
     */
    public function annual(int $year, ?string $accountId): array
    {
        $monthLabels = [];
        $incomes     = [];
        $expenses    = [];

        for ($m = 1; $m <= 12; $m++) {
            $from = Carbon::create($year, $m, 1)->startOfMonth()->toDateString();
            $to   = Carbon::create($year, $m, 1)->endOfMonth()->toDateString();

            $q = Transaction::whereBetween('date', [$from, $to])
                ->when($accountId, fn ($query) => $query->where('account_id', $accountId));

            // Regla 4.3: interés fuera del ingreso operativo
            $inc = bcadd((string) ($q->clone()->where('type', 'income')->sum('amount') ?: 0), '0', 2);
            $exp = bcadd((string) ($q->clone()->whereIn('type', ['expense', 'fee'])->sum('amount') ?: 0), '0', 2);

            $monthLabels[] = Carbon::create($year, $m, 1)->translatedFormat('M');
            $incomes[]     = $inc;
            $expenses[]    = $exp;
        }

        $totalIncome  = bcsum($incomes);
        $totalExpense = bcsum($expenses);
        $netBalance   = bcsub($totalIncome, $totalExpense, 2);

        // Tabla mensual con balance acumulado (bcmath, sin acarreo float)
        $rows       = [];
        $cumulative = '0.00';

        for ($m = 0; $m < 12; $m++) {
            $net        = bcsub($incomes[$m], $expenses[$m], 2);
            $cumulative = bcadd($cumulative, $net, 2);

            $rows[] = [
                'label'      => $monthLabels[$m],
                'income'     => $incomes[$m],
                'expense'    => $expenses[$m],
                'net'        => $net,
                'cumulative' => $cumulative,
            ];
        }

        // Series para Chart.js (float solo aquí, al serializar)
        $incomes  = array_map(floatval(...), $incomes);
        $expenses = array_map(floatval(...), $expenses);

        return compact('monthLabels', 'incomes', 'expenses', 'totalIncome', 'totalExpense', 'netBalance', 'rows');
    }

    /**
     * Reporte de egresos por categoría del mes.
     */
    public function categories(string $month, ?string $accountId): array
    {
        [$from, $to] = $this->monthRange($month);

        $q = Transaction::with('category')
            ->whereIn('type', ['expense', 'fee'])
            ->whereBetween('date', [$from, $to])
            ->when($accountId, fn ($query) => $query->where('account_id', $accountId));

        $byCategory = $q->get()
            ->groupBy(fn ($tx) => $tx->category?->name ?? 'Sin categoría')
            ->map(fn ($txs) => [
                'name'  => $txs->first()->category?->name ?? 'Sin categoría',
                'color' => $txs->first()->category?->color ?? '#ababab',
                'icon'  => $txs->first()->category?->icon ?? 'tag',
                'total' => bcsum($txs->pluck('amount')),
                'count' => $txs->count(),
            ])
            ->sortByDesc(fn ($row) => (float) $row['total'])
            ->values();

        $totalExpense = bcsum($byCategory->pluck('total'));

        // Ingreso operativo del mes para contexto (regla 4.3: sin intereses)
        $totalIncome = bcadd((string) (Transaction::where('type', 'income')
            ->whereBetween('date', [$from, $to])
            ->when($accountId, fn ($query) => $query->where('account_id', $accountId))
            ->sum('amount') ?: 0), '0', 2);

        $catLabels = $byCategory->pluck('name')->toArray();
        $catTotals = $byCategory->pluck('total')->map(floatval(...))->toArray();
        $catColors = $byCategory->pluck('color')->toArray();

        return compact('byCategory', 'totalExpense', 'totalIncome', 'catLabels', 'catTotals', 'catColors');
    }

    /**
     * Reporte de ingreso operativo por fuente del mes + evolución 6 meses.
     */
    public function sources(string $month, ?string $accountId): array
    {
        [$from, $to] = $this->monthRange($month);

        $q = Transaction::with('source')
            ->where('type', 'income')
            ->whereBetween('date', [$from, $to])
            ->when($accountId, fn ($query) => $query->where('account_id', $accountId));

        $bySource = $q->get()
            ->groupBy(fn ($tx) => $tx->source?->name ?? 'Sin fuente')
            ->map(fn ($txs) => [
                'name'  => $txs->first()->source?->name ?? 'Sin fuente',
                'total' => bcsum($txs->pluck('amount')),
                'count' => $txs->count(),
            ])
            ->sortByDesc(fn ($row) => (float) $row['total'])
            ->values();

        $totalIncome = bcsum($bySource->pluck('total'));

        // Evolución mensual (últimos 6 meses) — float solo al serializar
        $srcLabels = [];
        $srcMonths = [];

        for ($i = 5; $i >= 0; $i--) {
            $d = Carbon::createFromFormat('Y-m', $month)->subMonths($i);
            $f = $d->copy()->startOfMonth()->toDateString();
            $t = $d->copy()->endOfMonth()->toDateString();

            $srcLabels[] = $d->translatedFormat('M y');
            $srcMonths[] = (float) (Transaction::where('type', 'income')
                ->whereBetween('date', [$f, $t])
                ->when($accountId, fn ($query) => $query->where('account_id', $accountId))
                ->sum('amount') ?: 0);
        }

        return compact('bySource', 'totalIncome', 'srcLabels', 'srcMonths');
    }

    private function monthRange(string $month): array
    {
        $carbon = Carbon::createFromFormat('Y-m', $month);

        return [
            $carbon->copy()->startOfMonth()->toDateString(),
            $carbon->copy()->endOfMonth()->toDateString(),
        ];
    }
}
