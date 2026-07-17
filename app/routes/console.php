<?php

use App\Models\RecurringCharge;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment('Keep going, Hans.');
})->purpose('Display an inspiring quote');

// ── Cargos recurrentes ────────────────────────────────────────────────
// Los cargos NUNCA se aplican solos: Hans da el visto bueno final en
// /recurring/{id}/apply (ajustando el monto si el cargo en USD varió).
// Este job solo avisa por Telegram cuáles ya vencieron.
Schedule::call(function () {
    $due = RecurringCharge::dueOn(now()->toDateString())->orderBy('next_application_date')->get();

    if ($due->isEmpty() || ! config('services.telegram.bot_token')) {
        return;
    }

    $lines = $due->map(fn (RecurringCharge $c) => '• ' . $c->name . ' — ' . format_currency($c->amount)
        . ' (vence ' . $c->next_application_date->translatedFormat('j M') . ')');

    app(TelegramService::class)->sendMessage(
        config('services.telegram.chat_id'),
        "📋 Cargos recurrentes esperando tu aplicación:\n\n" . $lines->implode("\n")
            . "\n\nAplícalos en https://fp.hanshatch.com/recurring"
    );
})->dailyAt('08:00')->name('notify-due-recurring-charges')->withoutOverlapping();
