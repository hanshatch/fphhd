<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Transferencias entre cuentas propias de las que solo se conoce un lado
 * (el estado de cuenta no dice a cuál cuenta fue). Se guardan como
 * pendientes y, al cargar la otra cuenta, se unen solas en una sola
 * transferencia origen → destino.
 */
class TransferReconcileService
{
    // Un SPEI puede aparecer con uno o dos días de diferencia entre bancos
    private const DAYS = 4;

    /**
     * Une los pares sin ambigüedad: una salida y una entrada del mismo monto,
     * en cuentas distintas y fechas cercanas, que solo se tienen la una a la
     * otra como candidato. Devuelve cuántas transferencias concilió.
     */
    public function autoMatch(): int
    {
        $merged = 0;

        $outs = Transaction::pendingTransfers()->where('transfer_pending', Transaction::PENDING_OUT)
            ->orderBy('date')->orderBy('id')->get();

        foreach ($outs as $out) {
            $ins = $this->opposites($out);

            if ($ins->count() === 1 && $this->opposites($ins->first())->count() === 1) {
                $this->merge($out, $ins->first());
                $merged++;
            }
        }

        if ($merged > 0) {
            AuditLog::record('transfer_reconcile', ['merged' => $merged]);
        }

        return $merged;
    }

    /**
     * Pendientes con sus candidatos, para resolver a mano los dudosos.
     * Cada candidato: ['tx' => Transaction, 'pending' => bool].
     */
    public function pendingWithCandidates(): Collection
    {
        return Transaction::pendingTransfers()->with('account')
            ->orderByDesc('date')->orderByDesc('id')->get()
            ->map(fn (Transaction $tx) => [
                'tx'         => $tx,
                'candidates' => $this->opposites($tx)->map(fn ($c) => ['tx' => $c, 'pending' => true])
                    ->concat($this->loose($tx)->map(fn ($c) => ['tx' => $c, 'pending' => false])),
            ]);
    }

    /** Une el pendiente con el movimiento elegido (otro pendiente, o un cargo/abono suelto de otra cuenta) */
    public function matchWith(Transaction $pending, Transaction $other): bool
    {
        if (! $pending->isPendingTransfer() || $other->account_id === $pending->account_id
            || bccomp((string) $pending->amount, (string) $other->amount, 2) !== 0) {
            return false;
        }

        $isOut = $pending->transfer_pending === Transaction::PENDING_OUT;

        if ($other->isPendingTransfer()) {
            if ($other->transfer_pending === $pending->transfer_pending) {
                return false;
            }

            $isOut ? $this->merge($pending, $other) : $this->merge($other, $pending);

            return true;
        }

        if ($other->type !== ($isOut ? Transaction::TYPE_INCOME : Transaction::TYPE_EXPENSE)) {
            return false;
        }

        DB::transaction(function () use ($pending, $other) {
            $other->delete();
            $this->assign($pending, $other->account);
        });

        return true;
    }

    /** El usuario dice cuál es la otra cuenta, aunque su lado no esté cargado */
    public function assign(Transaction $pending, Account $other): void
    {
        [$from, $to] = $pending->transfer_pending === Transaction::PENDING_OUT
            ? [$pending->account, $other]
            : [$other, $pending->account];

        // El modelo voltea origen/destino cuando el pendiente era una entrada
        $pending->update([
            'counterparty_account_id' => $other->id,
            'description'             => 'Transferencia ' . $from->displayLabel() . ' → ' . $to->displayLabel(),
        ]);
    }

    /** No era entre cuentas propias: queda como gasto o ingreso normal */
    public function toMovement(Transaction $pending): void
    {
        $pending->update([
            'type' => $pending->transfer_pending === Transaction::PENDING_OUT
                ? Transaction::TYPE_EXPENSE
                : Transaction::TYPE_INCOME,
        ]);
    }

    /** Pendiente del lado contrario que una captura/correo nuevo podría completar */
    public function pendingTwin(Account $account, string $direction, string $amount, string $date): ?Transaction
    {
        $twins = $this->window(Transaction::pendingTransfers()->with('account'), $amount, $date)
            ->where('account_id', '<>', $account->id)
            ->where('transfer_pending', $direction === 'out' ? Transaction::PENDING_IN : Transaction::PENDING_OUT)
            ->get();

        // Con dos candidatos no se adivina: se resuelve en la pantalla de pendientes
        return $twins->count() === 1 ? $twins->first() : null;
    }

    private function merge(Transaction $out, Transaction $in): void
    {
        DB::transaction(function () use ($out, $in) {
            $to = $in->account;
            $in->delete();
            $this->assign($out, $to);
        });
    }

    private function opposites(Transaction $tx): Collection
    {
        return $this->window(Transaction::pendingTransfers()->with('account'), (string) $tx->amount, $tx->date->toDateString())
            ->where('account_id', '<>', $tx->account_id)
            ->where('transfer_pending', '<>', $tx->transfer_pending)
            ->orderBy('date')->get();
    }

    /** Cargo/abono suelto en otra cuenta que podría ser el otro lado, registrado como gasto/ingreso */
    private function loose(Transaction $tx): Collection
    {
        return $this->window(Transaction::with('account'), (string) $tx->amount, $tx->date->toDateString())
            ->where('account_id', '<>', $tx->account_id)
            ->where('type', $tx->transfer_pending === Transaction::PENDING_OUT ? Transaction::TYPE_INCOME : Transaction::TYPE_EXPENSE)
            ->orderBy('date')->get();
    }

    private function window($query, string $amount, string $date)
    {
        $d = Carbon::parse($date);

        return $query->where('amount', $amount)
            ->whereDate('date', '>=', $d->copy()->subDays(self::DAYS)->toDateString())
            ->whereDate('date', '<=', $d->copy()->addDays(self::DAYS)->toDateString());
    }
}
