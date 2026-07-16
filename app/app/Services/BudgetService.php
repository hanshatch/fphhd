<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class BudgetService
{
    /**
     * Presupuestos con gasto real del mes en UNA sola query agregada
     * (antes: 2 queries por presupuesto).
     */
    public function overview(?Carbon $month = null): array
    {
        $month ??= now()->startOfMonth();

        $budgets = Budget::with('category')->get()
            ->sortBy(fn ($b) => $b->category?->name)
            ->values();

        $spentByCategory = $this->spentByCategory($budgets->pluck('category_id')->all(), $month);

        $items = $budgets->map(function ($b) use ($spentByCategory) {
            $spent = bcadd((string) ($spentByCategory[$b->category_id] ?? 0), '0', 2);
            $limit = (string) $b->amount;

            return [
                'budget'  => $b,
                'spent'   => $spent,
                'percent' => bccomp($limit, '0', 2) > 0
                    ? round((float) bcdiv(bcmul($spent, '100', 4), $limit, 4), 1)
                    : 0.0,
            ];
        });

        return [
            'budgets'    => $items,
            'totalLimit' => bcsum($budgets->pluck('amount')),
            'totalSpent' => bcsum($items->pluck('spent')),
        ];
    }

    /**
     * Cuántos presupuestos superan el umbral de uso (default 80%).
     */
    public function countAtRisk(int $threshold = 80, ?Carbon $month = null): int
    {
        return collect($this->overview($month)['budgets'])
            ->filter(fn ($item) => $item['percent'] >= $threshold)
            ->count();
    }

    /**
     * Gasto real por categoría en el mes: [category_id => total].
     */
    private function spentByCategory(array $categoryIds, Carbon $month): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $from = $month->copy()->startOfMonth()->toDateString();
        $to   = $month->copy()->endOfMonth()->toDateString();

        return Transaction::whereIn('category_id', $categoryIds)
            ->whereIn('type', ['expense', 'fee'])
            ->whereBetween('date', [$from, $to])
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id')
            ->all();
    }
}
