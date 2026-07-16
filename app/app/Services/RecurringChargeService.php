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
     */
    public function applyCharge(RecurringCharge $charge): Transaction
    {
        $label = $charge->is_msi
            ? "{$charge->name} — cuota " . ($charge->applied_installments + 1) . " de {$charge->total_installments}"
            : $charge->name;

        $transaction = Transaction::create([
            'date'        => $charge->next_application_date->toDateString(),
            'type'        => $charge->type,
            'amount'      => $charge->amount,
            'account_id'  => $charge->account_id,
            'category_id' => $charge->category_id,
            'description' => $label,
        ]);

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

        return $transaction;
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
