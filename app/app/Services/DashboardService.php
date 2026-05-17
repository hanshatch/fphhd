<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(private AccountService $accountService) {}

    /**
     * Resumen del mes: ingresos, egresos, neto.
     * Solo tipos operativos (excluye transferencias internas).
     */
    public function monthlyFlow(?Carbon $month = null): array
    {
        $month ??= now();
        $from = $month->copy()->startOfMonth()->toDateString();
        $to   = $month->copy()->endOfMonth()->toDateString();

        $rows = Transaction::whereBetween('date', [$from, $to])
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $income   = bcadd((string)($rows['income']   ?? 0), (string)($rows['interest'] ?? 0), 2);
        $expenses = bcadd((string)($rows['expense']  ?? 0), (string)($rows['fee']      ?? 0), 2);
        $net      = bcsub($income, $expenses, 2);

        return compact('income', 'expenses', 'net', 'from', 'to');
    }

    /**
     * Datos para gráfica de barras: últimos N meses.
     * Retorna array con labels, income[], expenses[].
     */
    public function monthlyChart(int $months = 6): array
    {
        $labels   = [];
        $incomes  = [];
        $expenses = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $m    = now()->subMonths($i);
            $flow = $this->monthlyFlow($m);

            $labels[]   = $m->translatedFormat('M y');
            $incomes[]  = (float) $flow['income'];
            $expenses[] = (float) $flow['expenses'];
        }

        return compact('labels', 'incomes', 'expenses');
    }

    /**
     * Top N categorías de egreso del mes actual.
     */
    public function topExpenseCategories(int $limit = 5): Collection
    {
        $from = now()->startOfMonth()->toDateString();
        $to   = now()->endOfMonth()->toDateString();

        $rows = Transaction::whereBetween('date', [$from, $to])
            ->whereIn('type', ['expense', 'fee'])
            ->whereNotNull('category_id')
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->with('category')
            ->get();

        $max = $rows->max('total') ?: 1;

        return $rows->map(fn ($r) => [
            'name'    => $r->category->name ?? 'Sin categoría',
            'color'   => $r->category->color ?? '#ababab',
            'total'   => (float) $r->total,
            'percent' => round(((float) $r->total / $max) * 100),
        ]);
    }

    /**
     * Intereses ganados en el mes por cuenta de inversión/ahorro.
     */
    public function monthlyInterest(): Collection
    {
        $from = now()->startOfMonth()->toDateString();
        $to   = now()->endOfMonth()->toDateString();

        return Transaction::whereBetween('date', [$from, $to])
            ->where('type', 'interest')
            ->selectRaw('account_id, SUM(amount) as total')
            ->groupBy('account_id')
            ->with('account')
            ->get()
            ->map(fn ($r) => [
                'name'  => $r->account->name,
                'color' => $r->account->color,
                'total' => (float) $r->total,
            ]);
    }

    /**
     * Todo junto en una sola llamada para el dashboard.
     */
    public function summary(): array
    {
        $accounts = Account::where('is_active', true)->get()
            ->map(fn ($a) => [
                'account' => $a,
                'balance' => $this->accountService->balance($a),
            ]);

        return [
            'netWorth'          => $this->accountService->netWorth(),
            'accounts'          => $accounts,
            'flow'              => $this->monthlyFlow(),
            'chart'             => $this->monthlyChart(6),
            'topCategories'     => $this->topExpenseCategories(5),
            'monthlyInterest'   => $this->monthlyInterest(),
            'recent'            => Transaction::with('account', 'category', 'source')
                                    ->orderBy('date', 'desc')->orderBy('id', 'desc')
                                    ->limit(8)->get(),
        ];
    }
}
