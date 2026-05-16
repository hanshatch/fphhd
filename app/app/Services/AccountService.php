<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;

class AccountService
{
    /**
     * Calcula el saldo actual de una cuenta.
     * Nunca usa float — opera con strings y bcmath.
     */
    public function balance(Account $account, ?string $asOf = null): string
    {
        $query = Transaction::where('account_id', $account->id);
        $transferIn = Transaction::where('counterparty_account_id', $account->id);

        if ($asOf) {
            $query->whereDate('date', '<=', $asOf);
            $transferIn->whereDate('date', '<=', $asOf);
        }

        $balance = (string) $account->initial_balance;

        if ($account->isCredit()) {
            // TDC: crece con gastos, baja con pagos (transfers entrantes)
            $expenses = (string) ($query->whereIn('type', ['expense', 'fee'])->sum('amount') ?? 0);
            $payments = (string) ($transferIn->where('type', 'transfer')->sum('amount') ?? 0);
            $balance  = bcadd($balance, $expenses, 2);
            $balance  = bcsub($balance, $payments, 2);
        } else {
            // Débito / Ahorro / Inversión / Efectivo
            $income    = (string) ($query->whereIn('type', ['income', 'interest'])->sum('amount') ?? 0);
            $expenses  = (string) ($query->whereIn('type', ['expense', 'fee'])->sum('amount') ?? 0);
            $transOut  = (string) ($query->where('type', 'transfer')->sum('amount') ?? 0);
            $transIn   = (string) ($transferIn->where('type', 'transfer')->sum('amount') ?? 0);

            $balance = bcadd($balance, $income, 2);
            $balance = bcsub($balance, $expenses, 2);
            $balance = bcsub($balance, $transOut, 2);
            $balance = bcadd($balance, $transIn, 2);
        }

        return $balance;
    }

    /**
     * Saldo total de activos (no TDC) menos pasivos (TDC).
     */
    public function netWorth(): string
    {
        $accounts = Account::where('is_active', true)->get();
        $net      = '0';

        foreach ($accounts as $account) {
            $bal = $this->balance($account);
            $net = $account->isCredit()
                ? bcsub($net, $bal, 2)
                : bcadd($net, $bal, 2);
        }

        return $net;
    }
}
