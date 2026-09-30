<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

/**
 * Empata un movimiento detectado (captura o correo) contra lo ya registrado:
 *
 *  - existing():     ¿ya está registrado en ESTA cuenta? Incluye transferencias
 *                    en cualquier sentido, para no duplicar una transferencia
 *                    que se capturó desde la otra cuenta.
 *  - transferTwin(): ¿la otra mitad está en OTRA cuenta como cargo/abono suelto?
 *                    (ej. +$20,000 en Revolut y −$20,000 en Cheques el mismo día)
 *
 * $direction: 'out' = sale dinero de la cuenta, 'in' = entra.
 */
class TransactionMatchService
{
    public function existing(Account $account, string $direction, string $amount, string $date, int $days = 3): ?Transaction
    {
        return $this->window(Transaction::with('account', 'counterpartyAccount'), $amount, $date, $days)
            ->where(function ($q) use ($account, $direction) {
                if ($direction === 'out') {
                    $q->where('account_id', $account->id)
                        ->whereIn('type', [Transaction::TYPE_EXPENSE, Transaction::TYPE_FEE, Transaction::TYPE_TRANSFER]);
                } else {
                    $q->where(fn ($q) => $q->where('account_id', $account->id)
                            ->whereIn('type', [Transaction::TYPE_INCOME, Transaction::TYPE_INTEREST]))
                        ->orWhere(fn ($q) => $q->where('counterparty_account_id', $account->id)
                            ->where('type', Transaction::TYPE_TRANSFER));
                }
            })
            ->orderByDesc('id')
            ->first();
    }

    public function transferTwin(Account $account, string $direction, string $amount, string $date, int $days = 3): ?Transaction
    {
        return $this->window(Transaction::with('account'), $amount, $date, $days)
            ->where('account_id', '<>', $account->id)
            ->where('type', $direction === 'out' ? Transaction::TYPE_INCOME : Transaction::TYPE_EXPENSE)
            ->orderByDesc('id')
            ->first();
    }

    /** Convierte un cargo/abono suelto en la transferencia origen → destino */
    public function convertToTransfer(Transaction $twin, Account $from, Account $to): Transaction
    {
        $twin->update([
            'type'                    => Transaction::TYPE_TRANSFER,
            'account_id'              => $from->id,
            'counterparty_account_id' => $to->id,
            'category_id'             => null,
            'source_id'               => null,
            'description'             => 'Transferencia ' . $from->displayLabel() . ' → ' . $to->displayLabel(),
        ]);

        return $twin->fresh(['account', 'counterpartyAccount']);
    }

    /** Resumen legible de un movimiento empatado, para avisos */
    public function describe(Transaction $tx, Account $from): array
    {
        $where = $tx->type === Transaction::TYPE_TRANSFER
            ? 'Transferencia ' . ($tx->account?->name ?? '?') . ' → ' . ($tx->counterpartyAccount?->name ?? '?')
            : ($tx->account?->name ?? '');

        return [
            'id'          => $tx->id,
            'amount'      => (string) $tx->amount,
            'description' => $tx->description ?: 'Sin descripción',
            'account'     => $where,
            'date'        => $tx->date->translatedFormat('j M Y'),
        ];
    }

    private function window($query, string $amount, string $date, int $days)
    {
        $d = Carbon::parse($date);

        return $query->where('amount', $amount)
            ->whereDate('date', '>=', $d->copy()->subDays($days)->toDateString())
            ->whereDate('date', '<=', $d->copy()->addDays($days)->toDateString());
    }
}
