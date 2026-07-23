<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment('Keep going, Hans.');
})->purpose('Display an inspiring quote');

// ─────────────────────────────────────────────────────────────────────
// El cron de Hostinger corre `php artisan schedule:run` CADA 5 MINUTOS.
// ⚠️ Toda tarea nueva debe programarse en minutos múltiplos de 5
// (dailyAt('08:00') sí; dailyAt('08:03') jamás se ejecutaría).
// ─────────────────────────────────────────────────────────────────────

// Cargos recurrentes: NUNCA se aplican solos. Cada vencido genera SU
// PROPIA notificación de Telegram con botones (aplicar / ajustar / hoy no).
Schedule::command('telegram:notify-due')
    ->dailyAt('08:00')
    ->name('notify-due-recurring-charges')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));
