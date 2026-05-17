<?php

use App\Services\RecurringChargeService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment('Keep going, Hans.');
})->purpose('Display an inspiring quote');

// ── Cargos recurrentes ────────────────────────────────────────────────
// Corre diariamente a las 6:00 AM y aplica todos los cargos vencidos
Schedule::call(function () {
    $service = app(RecurringChargeService::class);
    $applied = $service->processDueCharges();

    if ($applied->isNotEmpty()) {
        logger()->info("RecurringCharges: {$applied->count()} cargo(s) aplicado(s)", [
            'charges' => $applied->pluck('name')->toArray(),
        ]);
    }
})->dailyAt('06:00')->name('apply-recurring-charges')->withoutOverlapping();
