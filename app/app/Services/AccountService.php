<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class AccountService
{
    /**
     * Calcula el saldo actual de una cuenta.
     * Nunca usa float — opera con strings y bcmath.
     */
    public function balance(Account $account, ?string $asOf = null): string
    {
        // OJO: cada agregación necesita su propio builder — encadenar whereIn
        // sobre el mismo query acumula condiciones y da sumas vacías.
        $query = fn () => Transaction::where('account_id', $account->id)
            ->when($asOf, fn ($q) => $q->whereDate('date', '<=', $asOf));

        $transferIn = fn () => Transaction::where('counterparty_account_id', $account->id)
            ->when($asOf, fn ($q) => $q->whereDate('date', '<=', $asOf));

        $balance = (string) $account->initial_balance;

        if ($account->isCredit()) {
            // TDC: la deuda crece con gastos/comisiones y baja con pagos
            // (transfers entrantes) y reembolsos/bonificaciones (income/interest)
            $expenses = (string) ($query()->whereIn('type', ['expense', 'fee'])->sum('amount') ?: 0);
            $payments = (string) ($transferIn()->where('type', 'transfer')->sum('amount') ?: 0);
            $refunds  = (string) ($query()->whereIn('type', ['income', 'interest'])->sum('amount') ?: 0);

            $balance = bcadd($balance, $expenses, 2);
            $balance = bcsub($balance, $payments, 2);
            $balance = bcsub($balance, $refunds, 2);
        } else {
            // Débito / Ahorro / Inversión / Efectivo
            $income   = (string) ($query()->whereIn('type', ['income', 'interest'])->sum('amount') ?: 0);
            $expenses = (string) ($query()->whereIn('type', ['expense', 'fee'])->sum('amount') ?: 0);
            $transOut = (string) ($query()->where('type', 'transfer')->sum('amount') ?: 0);
            $transIn  = (string) ($transferIn()->where('type', 'transfer')->sum('amount') ?: 0);

            $balance = bcadd($balance, $income, 2);
            $balance = bcsub($balance, $expenses, 2);
            $balance = bcsub($balance, $transOut, 2);
            $balance = bcadd($balance, $transIn, 2);
        }

        return $balance;
    }

    /**
     * Saldos de un conjunto de cuentas en solo 2 queries agregadas
     * (en lugar de 4 queries por cuenta). Retorna [account_id => saldo].
     */
    public function balances(Collection $accounts): array
    {
        $ids = $accounts->pluck('id');

        $ownByAccount = Transaction::whereIn('account_id', $ids)
            ->selectRaw('account_id, type, SUM(amount) as total')
            ->groupBy('account_id', 'type')
            ->get()
            ->groupBy('account_id');

        $incoming = Transaction::whereIn('counterparty_account_id', $ids)
            ->where('type', 'transfer')
            ->selectRaw('counterparty_account_id as aid, SUM(amount) as total')
            ->groupBy('counterparty_account_id')
            ->pluck('total', 'aid');

        $balances = [];

        foreach ($accounts as $account) {
            $byType = ($ownByAccount[$account->id] ?? collect())->pluck('total', 'type');
            $sumOf  = fn (array $types) => bcsum(array_map(fn ($t) => $byType[$t] ?? '0', $types));
            $transIn = bcadd((string) ($incoming[$account->id] ?? 0), '0', 2);

            $balance = (string) $account->initial_balance;

            if ($account->isCredit()) {
                $balance = bcadd($balance, $sumOf(['expense', 'fee']), 2);
                $balance = bcsub($balance, $transIn, 2);
                $balance = bcsub($balance, $sumOf(['income', 'interest']), 2);
            } else {
                $balance = bcadd($balance, $sumOf(['income', 'interest']), 2);
                $balance = bcsub($balance, $sumOf(['expense', 'fee']), 2);
                $balance = bcsub($balance, $sumOf(['transfer']), 2);
                $balance = bcadd($balance, $transIn, 2);
            }

            $balances[$account->id] = $balance;
        }

        return $balances;
    }

    /**
     * Saldo total de activos (no TDC) menos pasivos (TDC).
     * Acepta saldos precalculados para no recomputar.
     */
    public function netWorth(?Collection $accounts = null, ?array $balances = null): string
    {
        $accounts ??= Account::where('is_active', true)->get();
        $balances ??= $this->balances($accounts);

        $net = '0.00';

        foreach ($accounts as $account) {
            $bal = $balances[$account->id] ?? '0.00';
            $net = $account->isCredit()
                ? bcsub($net, $bal, 2)
                : bcadd($net, $bal, 2);
        }

        return $net;
    }

    /**
     * Cuentas agrupadas por tipo (orden estilo MoneyWiz) con saldos y
     * totales por grupo. Para el listado de cuentas.
     */
    public function groupedWithBalances(): array
    {
        $typeOrder = ['debit' => 0, 'savings' => 1, 'investment' => 2, 'cash' => 3, 'credit' => 4];

        $accounts = Account::orderBy('name')->get();
        $balances = $this->balances($accounts);

        $groups = $accounts
            ->map(fn ($a) => ['account' => $a, 'balance' => $balances[$a->id]])
            ->groupBy(fn ($item) => $item['account']->type)
            ->sortBy(fn ($items, $type) => $typeOrder[$type] ?? 99);

        $groupTotals = $groups->map(fn ($items) => bcsum($items->pluck('balance')));

        $active   = $accounts->where('is_active', true);
        $netWorth = $this->netWorth($active->values(), $balances);

        return compact('groups', 'groupTotals', 'netWorth');
    }

    /**
     * Movimientos de la cuenta (incluyendo transferencias entrantes) con
     * saldo corrido bcmath, espejo exacto de balance().
     */
    public function runningBalances(Account $account): array
    {
        $transactions = Transaction::with(['account', 'category', 'source', 'counterpartyAccount'])
            ->where(fn ($q) => $q
                ->where('account_id', $account->id)
                ->orWhere(fn ($q2) => $q2
                    ->where('counterparty_account_id', $account->id)
                    ->where('type', 'transfer')))
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $running  = (string) $account->initial_balance;
        $balances = [];

        foreach ($transactions as $tx) {
            $amount     = (string) $tx->amount;
            $isIncoming = $tx->type === 'transfer' && $tx->counterparty_account_id === $account->id;

            if ($account->isCredit()) {
                // Deuda: sube con gastos/comisiones, baja con pagos y reembolsos
                $running = match (true) {
                    in_array($tx->type, ['expense', 'fee'])      => bcadd($running, $amount, 2),
                    $isIncoming                                  => bcsub($running, $amount, 2),
                    in_array($tx->type, ['income', 'interest'])  => bcsub($running, $amount, 2),
                    default                                      => $running,
                };
            } else {
                $running = match (true) {
                    in_array($tx->type, ['income', 'interest'])  => bcadd($running, $amount, 2),
                    $isIncoming                                  => bcadd($running, $amount, 2),
                    default                                      => bcsub($running, $amount, 2),
                };
            }

            $balances[$tx->id] = $running;
        }

        return [$transactions, $balances];
    }

    /**
     * Ajusta el saldo de la cuenta al valor objetivo creando un movimiento.
     * En TDC el saldo es deuda: subirla = expense, bajarla = income.
     * Retorna null si no hubo diferencia.
     */
    public function adjustBalance(Account $account, string $target, string $date, ?string $description = null): ?Transaction
    {
        $current = $this->balance($account);
        $diff    = bcsub(parse_money($target), $current, 2);

        if (bccomp($diff, '0', 2) === 0) {
            return null;
        }

        $increase = bccomp($diff, '0', 2) > 0;
        $type     = $account->isCredit()
            ? ($increase ? 'expense' : 'income')
            : ($increase ? 'income' : 'expense');

        return Transaction::create([
            'date'        => $date,
            'type'        => $type,
            'amount'      => ltrim($diff, '-'),
            'account_id'  => $account->id,
            'description' => $description ?: 'Ajuste de saldo',
        ]);
    }
}
