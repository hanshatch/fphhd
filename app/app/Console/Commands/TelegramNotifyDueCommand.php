<?php

namespace App\Console\Commands;

use App\Services\RecurringChargeService;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramNotifyDueCommand extends Command
{
    protected $signature = 'telegram:notify-due';

    protected $description = 'Notifica por Telegram cada cargo recurrente vencido, con botones para aplicar u omitir';

    public function handle(RecurringChargeService $service, TelegramService $telegram): int
    {
        $count = $service->notifyDueViaTelegram($telegram);

        $this->info($count === 0 ? 'Sin cargos vencidos por notificar.' : "{$count} notificación(es) enviada(s).");

        return self::SUCCESS;
    }
}
