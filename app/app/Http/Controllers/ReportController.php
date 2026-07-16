<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Source;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $type  = $request->get('type', 'annual');
        $year  = (int) $request->get('year', now()->year);
        $month = $request->get('month', now()->format('Y-m'));

        $accounts = Account::where('is_active', true)->orderBy('name')->get();
        $accountId = $request->get('account_id');

        $data = match ($type) {
            'categories' => $this->categoriesReport($year, $month, $accountId),
            'sources'    => $this->sourcesReport($year, $month, $accountId),
            default      => $this->annualReport($year, $accountId),
        };

        return view('pages.reports.index', array_merge($data, [
            'type'      => $type,
            'year'      => $year,
            'month'     => $month,
            'accounts'  => $accounts,
            'accountId' => $accountId,
        ]));
    }

    // ── Reporte anual: ingresos vs egresos por mes ──────────────────

    private function annualReport(int $year, ?string $accountId): array
    {
        $monthLabels = [];
        $incomes     = [];
        $expenses    = [];

        for ($m = 1; $m <= 12; $m++) {
            $from = Carbon::create($year, $m, 1)->startOfMonth()->toDateString();
            $to   = Carbon::create($year, $m, 1)->endOfMonth()->toDateString();

            $q = Transaction::whereBetween('date', [$from, $to]);
            if ($accountId) $q->where('account_id', $accountId);

            $inc = (float) $q->clone()->where('type', 'income')->sum('amount');
            $exp = (float) $q->clone()->whereIn('type', ['expense', 'fee'])->sum('amount');

            $monthLabels[] = Carbon::create($year, $m, 1)->translatedFormat('M');
            $incomes[]     = round($inc, 2);
            $expenses[]    = round($exp, 2);
        }

        $totalIncome  = array_sum($incomes);
        $totalExpense = array_sum($expenses);
        $netBalance   = $totalIncome - $totalExpense;

        // Tabla mensual con balance acumulado
        $rows = [];
        $cumulative = 0;
        for ($m = 0; $m < 12; $m++) {
            $net = $incomes[$m] - $expenses[$m];
            $cumulative += $net;
            $rows[] = [
                'label'      => $monthLabels[$m],
                'income'     => $incomes[$m],
                'expense'    => $expenses[$m],
                'net'        => $net,
                'cumulative' => $cumulative,
            ];
        }

        return compact('monthLabels', 'incomes', 'expenses', 'totalIncome', 'totalExpense', 'netBalance', 'rows');
    }

    // ── Reporte por categoría ───────────────────────────────────────

    private function categoriesReport(int $year, string $month, ?string $accountId): array
    {
        $from = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
        $to   = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();

        $q = Transaction::with('category')
            ->whereIn('type', ['expense', 'fee'])
            ->whereBetween('date', [$from, $to]);
        if ($accountId) $q->where('account_id', $accountId);

        $byCategory = $q->get()
            ->groupBy(fn ($tx) => $tx->category?->name ?? 'Sin categoría')
            ->map(fn ($txs) => [
                'name'   => $txs->first()->category?->name ?? 'Sin categoría',
                'color'  => $txs->first()->category?->color ?? '#ababab',
                'total'  => round($txs->sum(fn ($t) => (float) $t->amount), 2),
                'count'  => $txs->count(),
            ])
            ->sortByDesc('total')
            ->values();

        $totalExpense = $byCategory->sum('total');

        // También ingresos del mes para contexto
        $qInc = Transaction::where('type', 'income')->whereBetween('date', [$from, $to]);
        if ($accountId) $qInc->where('account_id', $accountId);
        $totalIncome = (float) $qInc->sum('amount');

        $catLabels = $byCategory->pluck('name')->toArray();
        $catTotals = $byCategory->pluck('total')->toArray();
        $catColors = $byCategory->pluck('color')->toArray();

        return compact('byCategory', 'totalExpense', 'totalIncome', 'catLabels', 'catTotals', 'catColors');
    }

    // ── Reporte por fuente de ingreso ───────────────────────────────

    private function sourcesReport(int $year, string $month, ?string $accountId): array
    {
        $from = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
        $to   = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();

        $q = Transaction::with('source')
            ->where('type', 'income')
            ->whereBetween('date', [$from, $to]);
        if ($accountId) $q->where('account_id', $accountId);

        $bySource = $q->get()
            ->groupBy(fn ($tx) => $tx->source?->name ?? 'Sin fuente')
            ->map(fn ($txs) => [
                'name'  => $txs->first()->source?->name ?? 'Sin fuente',
                'total' => round($txs->sum(fn ($t) => (float) $t->amount), 2),
                'count' => $txs->count(),
            ])
            ->sortByDesc('total')
            ->values();

        $totalIncome = $bySource->sum('total');

        // Evolución mensual por fuente (últimos 6 meses)
        $srcLabels = [];
        $srcMonths = [];
        for ($i = 5; $i >= 0; $i--) {
            $d    = Carbon::createFromFormat('Y-m', $month)->subMonths($i);
            $f    = $d->copy()->startOfMonth()->toDateString();
            $t    = $d->copy()->endOfMonth()->toDateString();
            $srcLabels[] = $d->translatedFormat('M y');

            $qM = Transaction::with('source')
                ->where('type', 'income')
                ->whereBetween('date', [$f, $t]);
            if ($accountId) $qM->where('account_id', $accountId);

            $srcMonths[] = round((float) $qM->sum('amount'), 2);
        }

        return compact('bySource', 'totalIncome', 'srcLabels', 'srcMonths');
    }
}
