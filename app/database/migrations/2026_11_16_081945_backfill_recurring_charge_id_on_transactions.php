<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Liga a su cargo recurrente los movimientos que ya se habían aplicado antes
     * de existir transactions.recurring_charge_id. Al aplicar, el movimiento se
     * crea con el nombre del cargo ("Telmex") o "Nombre — cuota N de M".
     */
    public function up(): void
    {
        foreach (DB::table('recurring_charges')->get(['id', 'name', 'account_id', 'type']) as $charge) {
            DB::table('transactions')
                ->whereNull('recurring_charge_id')
                ->where('account_id', $charge->account_id)
                ->where('type', $charge->type)
                ->where(fn ($q) => $q->where('description', $charge->name)
                    ->orWhere('description', 'like', str_replace(['%', '_'], ['\\%', '\\_'], $charge->name) . ' — cuota %'))
                ->update(['recurring_charge_id' => $charge->id]);
        }
    }

    public function down(): void
    {
        // Sin reversa: no se distingue lo ligado aquí de lo ligado después
    }
};
