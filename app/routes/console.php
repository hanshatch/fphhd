<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment('Keep going, Hans.');
})->purpose('Display an inspiring quote');

// ── Cargos recurrentes ────────────────────────────────────────────────
// Los cargos NUNCA se aplican solos: cada vencido genera SU PROPIA
// notificación de Telegram con botones (aplicar / ajustar monto / hoy no)
// y Hans decide. Requiere cron con `php artisan schedule:run`.
Schedule::command('telegram:notify-due')
    ->dailyAt('08:00')
    ->name('notify-due-recurring-charges')
    ->withoutOverlapping();
