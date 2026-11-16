<?php

namespace App\Services;

use App\Models\RecurringCharge;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RecurringChargeService
{
    /**
     * Primera fecha de aplicación de un cargo nuevo: el day_of_month del mes
     * de start_date, o del mes siguiente si ese día ya quedó antes del inicio
     * (evita aplicar de inmediato un cargo con fecha ya pasada).
     */
    public function firstApplicationDate(string $startDate, int $day): string
    {
        $start = Carbon::parse($startDate);
        $first = $start->copy()->setDay(min($day, $start->daysInMonth));

        if ($first->lt($start)) {
            $next  = $start->copy()->addMonthNoOverflow()->startOfMonth();
            $first = $next->setDay(min($day, $next->daysInMonth));
        }

        return $first->toDateString();
    }

    /**
     * Aplica todos los cargos vencidos a hoy.
     * Se llama desde el scheduler diario a las 6:00 AM.
     * Retorna los cargos que fueron aplicados.
     */
    public function processDueCharges(?Carbon $asOf = null): Collection
    {
        $today   = ($asOf ?? Carbon::today())->toDateString();
        $charges = RecurringCharge::dueOn($today)->with('account', 'category')->get();
        $applied = collect();

        foreach ($charges as $charge) {
            $this->applyCharge($charge);
            $applied->push($charge);
        }

        return $applied;
    }

    /**
     * Aplica un cargo individual: crea la transacción y avanza la fecha.
     * $amount y $date opcionales permiten ajustar el monto final (cargos en
     * USD que varían con el tipo de cambio) y la fecha real del cobro.
     */
    public function applyCharge(RecurringCharge $charge, ?string $amount = null, ?string $date = null): Transaction
    {
        $label = $charge->is_msi
            ? "{$charge->name} — cuota " . ($charge->applied_installments + 1) . " de {$charge->total_installments}"
            : $charge->name;

        $transaction = Transaction::create([
            'date'        => $date ?? $charge->next_application_date->toDateString(),
            'type'        => $charge->type,
            'amount'      => $amount ?? $charge->amount,
            'account_id'  => $charge->account_id,
            'category_id' => $charge->category_id,
            'description' => $label,
            'recurring_charge_id' => $charge->id,
        ]);

        $this->advance($charge);

        return $transaction;
    }

    /**
     * Liga un movimiento que YA existe (llegó por correo, captura o a mano) como
     * la aplicación pendiente del cargo: no crea nada, solo cuenta la cuota y
     * avanza la próxima fecha. Devuelve false si no corresponde o ya estaba ligado.
     */
    public function linkTransaction(RecurringCharge $charge, Transaction $transaction): bool
    {
        if (! $charge->is_active || $transaction->recurring_charge_id
            || $charge->account_id !== $transaction->account_id || $charge->type !== $transaction->type) {
            return false;
        }

        $transaction->update([
            'recurring_charge_id' => $charge->id,
            'category_id'         => $transaction->category_id ?? $charge->category_id,
        ]);

        // Aprende cómo lo escribe el banco para reconocerlo solo la próxima vez. Un texto
        // genérico («Transferencia interbancaria») no sirve: empataría con cualquier movimiento.
        if (blank($charge->statement_text) && filled($transaction->description)
            && ($key = app(MerchantMemoryService::class)->key($transaction->description))
            && ! preg_match('/^(transferencia|traspaso|spei|retiro|dep[oó]sito|abono|pago|cargo|compra)\b/iu', $key)) {
            $charge->update(['statement_text' => $key]);
        }

        $this->advance($charge);

        // El movimiento es de un mes posterior al que estaba pendiente (los meses de en medio
        // nunca se registraron): la próxima fecha es la que sigue al mes del movimiento,
        // no un vencimiento que ya pasó. En compras a meses cada cuota cuenta, no se salta.
        if (! $charge->is_msi) {
            $charge->refresh();
            $monthEnd = $transaction->date->copy()->endOfMonth();

            while ($charge->is_active && $charge->next_application_date->lte($monthEnd)) {
                $next = $charge->calculateNextDate($charge->next_application_date);

                $charge->update([
                    'next_application_date' => $next->toDateString(),
                    'is_active'             => ! ($charge->end_date && $next->greaterThan($charge->end_date)),
                ]);
            }
        }

        return true;
    }

    /**
     * Deshace una liga hecha por error: el movimiento se queda como está y el
     * cargo vuelve a quedar pendiente para el mes de ese movimiento.
     */
    public function unlinkTransaction(Transaction $transaction): ?RecurringCharge
    {
        $charge = $transaction->recurringCharge;

        if (! $charge) {
            return null;
        }

        $month   = $transaction->date->copy()->startOfMonth();
        $pending = $month->copy()->setDay(min($charge->day_of_month, $month->daysInMonth));

        $charge->update([
            'applied_installments'  => max(0, $charge->applied_installments - 1),
            'next_application_date' => min($pending->toDateString(), $charge->next_application_date->toDateString()),
            'is_active'             => true,
        ]);

        $transaction->update(['recurring_charge_id' => null]);

        return $charge;
    }

    /** Cuenta una aplicación del cargo y avanza su próxima fecha (o lo cierra si ya terminó) */
    private function advance(RecurringCharge $charge): void
    {
        $newApplied = $charge->applied_installments + 1;

        // ¿Se completaron todas las cuotas?
        $isCompleted = $charge->total_installments && $newApplied >= $charge->total_installments;

        if ($isCompleted) {
            $charge->update([
                'applied_installments'  => $newApplied,
                'is_active'             => false,
            ]);
        } else {
            $nextDate = $charge->calculateNextDate($charge->next_application_date);

            // Si la siguiente fecha supera el end_date, desactivar
            $pastEnd = $charge->end_date && $nextDate->greaterThan($charge->end_date);

            $charge->update([
                'applied_installments'  => $newApplied,
                'next_application_date' => $nextDate->toDateString(),
                'is_active'             => ! $pastEnd,
            ]);
        }
    }

    /**
     * Omite la ocurrencia pendiente: avanza la próxima fecha un mes sin crear
     * movimiento ni contar cuota. Si la nueva fecha supera end_date, desactiva.
     * Devuelve la nueva fecha (o null si el cargo quedó desactivado).
     */
    public function skipCurrent(RecurringCharge $charge): ?Carbon
    {
        $nextDate = $charge->calculateNextDate($charge->next_application_date);
        $pastEnd  = $charge->end_date && $nextDate->greaterThan($charge->end_date);

        $charge->update([
            'next_application_date' => $nextDate->toDateString(),
            'is_active'             => ! $pastEnd,
        ]);

        return $pastEnd ? null : $nextDate;
    }

    /**
     * Manda una notificación de Telegram POR CADA cargo vencido, con botones
     * para aplicarlo (monto configurado), ajustar el monto en la web u omitir.
     * Devuelve cuántas notificaciones se enviaron.
     */
    public function notifyDueViaTelegram(TelegramService $telegram): int
    {
        if (! config('services.telegram.bot_token') || ! config('services.telegram.chat_id')) {
            return 0;
        }

        $due = RecurringCharge::dueOn(Carbon::today()->toDateString())
            ->with('account', 'category')
            ->orderBy('next_application_date')
            ->get();

        foreach ($due as $charge) {
            $msi = $charge->is_msi
                ? ' (cuota ' . ($charge->applied_installments + 1) . ' de ' . $charge->total_installments . ')'
                : '';

            $telegram->sendMessage(
                config('services.telegram.chat_id'),
                "📅 Cargo recurrente por aplicar:\n\n"
                    . format_currency($charge->amount) . ' · ' . $charge->name . $msi . "\n"
                    . $charge->account->displayLabel()
                    . ($charge->category ? ' · ' . $charge->category->name : '')
                    . "\nVence: " . $charge->next_application_date->translatedFormat('j M Y'),
                [[
                    ['text' => '✅ Aplicar ' . format_currency($charge->amount), 'callback_data' => 'rec:apply:' . $charge->id],
                ], [
                    ['text' => '✏️ Ajustar monto', 'url' => route('recurring.apply.show', $charge)],
                    ['text' => '⏭ Hoy no', 'callback_data' => 'rec:skip:' . $charge->id],
                ]]
            );
        }

        return $due->count();
    }

    /**
     * Tablero del mes para la pantalla de cargos recurrentes. Cada cargo cae en
     * un solo estado, para que se lea de un vistazo qué falta y qué ya quedó:
     *
     *  - overdue:  ya venció y no se ha aplicado (incluye meses anteriores)
     *  - upcoming: vence de hoy a fin de mes
     *  - done:     movimientos de este mes ligados a un cargo  [charge, transaction]
     *  - skipped:  su fecha de este mes ya pasó sin movimiento (se omitió)
     */
    public function monthBoard(?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $start = $today->copy()->startOfMonth();
        $end   = $today->copy()->endOfMonth();

        $applied = Transaction::whereNotNull('recurring_charge_id')
            ->whereDate('date', '>=', $start->toDateString())->whereDate('date', '<=', $end->toDateString())
            ->orderBy('date')->get()->groupBy('recurring_charge_id');

        $board = ['overdue' => collect(), 'upcoming' => collect(), 'done' => collect(), 'skipped' => collect()];

        foreach (RecurringCharge::with('account', 'category')->orderBy('next_application_date')->get() as $charge) {
            $txs = $applied->get($charge->id, collect());

            foreach ($txs as $tx) {
                $board['done']->push(['charge' => $charge, 'transaction' => $tx]);
            }

            if (! $charge->is_active) {
                continue;
            }

            $next = $charge->next_application_date->copy()->startOfDay();

            if ($next->lt($today)) {
                $board['overdue']->push($charge);
            } elseif ($next->lte($end)) {
                $board['upcoming']->push($charge);
            } elseif ($txs->isEmpty()) {
                $thisMonth = $start->copy()->setDay(min($charge->day_of_month, $start->daysInMonth));

                if ($charge->start_date && $charge->start_date->lte($thisMonth)) {
                    $board['skipped']->push($charge);
                }
            }
        }

        $sum = fn ($amounts) => $amounts->reduce(fn ($carry, $a) => bcadd($carry, (string) $a, 2), '0.00');

        $board['pending_total'] = $sum($board['overdue']->merge($board['upcoming'])->pluck('amount'));
        $board['done_total']    = $sum($board['done']->map(fn ($d) => $d['transaction']->amount));
        $board['month_total']   = bcadd($board['pending_total'], $board['done_total'], 2);
        $board['progress']      = bccomp($board['month_total'], '0', 2) > 0
            ? (int) round(((float) $board['done_total'] / (float) $board['month_total']) * 100)
            : 0;

        return $board;
    }

    /**
     * Cargos pendientes que caen dentro del mes vigente (de hoy a fin de mes).
     */
    public function upcomingThisMonth(): Collection
    {
        $from = Carbon::today()->toDateString();
        $to   = Carbon::today()->endOfMonth()->toDateString();

        return RecurringCharge::active()
            ->whereBetween('next_application_date', [$from, $to])
            ->with('account', 'category')
            ->orderBy('next_application_date')
            ->get();
    }

    /**
     * Cargos próximos en los siguientes N días (para el dashboard).
     */
    public function upcoming(int $days = 30): Collection
    {
        $from = Carbon::today()->toDateString();
        $to   = Carbon::today()->addDays($days)->toDateString();

        return RecurringCharge::active()
            ->whereBetween('next_application_date', [$from, $to])
            ->with('account', 'category')
            ->orderBy('next_application_date')
            ->get();
    }
}
