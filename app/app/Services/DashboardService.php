<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Budget;
use App\Models\IncomePlan;
use App\Models\RecurringCharge;
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

        // Regla 4.3: el interés NO es ingreso operativo (solo cuenta en rendimientos)
        $income   = bcadd((string)($rows['income']  ?? 0), '0', 2);
        $expenses = bcadd((string)($rows['expense'] ?? 0), (string)($rows['fee'] ?? 0), 2);
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

    // ── Fase 7: alertas de TDC ─────────────────────────────────────

    /**
     * Retorna TDCs con días restantes hasta corte y pago.
     * Alerta cuando faltan ≤ 7 días.
     */
    public function tdcAlerts(): Collection
    {
        $today = now();

        return Account::where('type', 'credit')
            ->where('is_active', true)
            ->with('creditCard')
            ->get()
            ->filter(fn ($a) => $a->creditCard !== null)
            ->map(function ($account) use ($today) {
                $cc      = $account->creditCard;
                $balance = $this->accountService->balance($account);

                // Calcular próxima fecha de corte y pago en el mes actual o siguiente
                $statDay = (int) $cc->statement_day;
                $payDay  = (int) $cc->payment_day;

                $nextStatement = $today->copy()->setDay(min($statDay, $today->daysInMonth));
                if ($nextStatement->isPast() || $nextStatement->isToday()) {
                    $nextStatement = $today->copy()->addMonthNoOverflow()->setDay(
                        min($statDay, $today->copy()->addMonthNoOverflow()->daysInMonth)
                    );
                }

                $nextPayment = $today->copy()->setDay(min($payDay, $today->daysInMonth));
                if ($nextPayment->isPast() || $nextPayment->isToday()) {
                    $nextPayment = $today->copy()->addMonthNoOverflow()->setDay(
                        min($payDay, $today->copy()->addMonthNoOverflow()->daysInMonth)
                    );
                }

                $daysToStatement = (int) $today->diffInDays($nextStatement, false);
                $daysToPayment   = (int) $today->diffInDays($nextPayment, false);

                $utilization = $cc->credit_limit > 0
                    ? round(abs((float) $balance) / (float) $cc->credit_limit * 100, 1)
                    : 0;

                return [
                    'account'       => $account,
                    'balance'       => $balance,
                    'credit_limit'  => $cc->credit_limit,
                    'utilization'   => $utilization,
                    'next_statement'=> $nextStatement,
                    'next_payment'  => $nextPayment,
                    'days_statement'=> $daysToStatement,
                    'days_payment'  => $daysToPayment,
                    'alert'         => $daysToStatement <= 7 || $daysToPayment <= 7,
                ];
            });
    }

    // ── Fase 9: indicadores financieros ────────────────────────────

    /**
     * Indicadores de salud financiera del mes actual.
     */
    public function financialIndicators(): array
    {
        $today   = now();
        $from    = $today->copy()->startOfMonth()->toDateString();
        $to      = $today->copy()->endOfMonth()->toDateString();
        $elapsed = $today->day;
        $daysInMonth = $today->daysInMonth;

        $flow = $this->monthlyFlow();

        // Tasa de ahorro
        $income   = (float) $flow['income'];
        $expenses = (float) $flow['expenses'];
        $savingsRate = $income > 0 ? round(($income - $expenses) / $income * 100, 1) : null;

        // Proyección de gasto al fin de mes (pace actual)
        $dailyPace  = $elapsed > 0 ? $expenses / $elapsed : 0;
        $projected  = round($dailyPace * $daysInMonth, 2);

        // Próximos 15 días: ingresos esperados - cargos recurrentes
        $next15from = $today->toDateString();
        $next15to   = $today->copy()->addDays(14)->toDateString();

        $incomePlanned = bcadd((string) (IncomePlan::active()
            ->whereBetween('next_expected_date', [$next15from, $next15to])
            ->sum('expected_amount') ?: 0), '0', 2);

        $chargesPlanned = bcadd((string) (RecurringCharge::active()
            ->whereBetween('next_application_date', [$next15from, $next15to])
            ->sum('amount') ?: 0), '0', 2);

        $quincenaDisponible = bcsub($incomePlanned, $chargesPlanned, 2);

        // Alertas de presupuesto: cuántos están en riesgo (>80%)
        $budgetsAtRisk = Budget::with('category')->get()
            ->filter(fn ($b) => $b->percentUsed() >= 80)
            ->count();

        return [
            'savings_rate'        => $savingsRate,
            'daily_pace'          => round($dailyPace, 2),
            'projected_expense'   => $projected,
            'income_month'        => $income,
            'expense_month'       => $expenses,
            'quincena_income'     => (float) $incomePlanned,
            'quincena_charges'    => (float) $chargesPlanned,
            'quincena_available'  => (float) $quincenaDisponible,
            'budgets_at_risk'     => $budgetsAtRisk,
            'days_elapsed'        => $elapsed,
            'days_in_month'       => $daysInMonth,
        ];
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
            'tdcAlerts'         => $this->tdcAlerts(),
            'indicators'        => $this->financialIndicators(),
            'recent'            => Transaction::with('account', 'category', 'source')
                                    ->orderBy('date', 'desc')->orderBy('id', 'desc')
                                    ->limit(8)->get(),
        ];
    }
}
