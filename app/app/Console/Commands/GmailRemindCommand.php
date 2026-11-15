<?php

namespace App\Console\Commands;

use App\Services\Mail\BankEmailImportService;
use Illuminate\Console\Command;

class GmailRemindCommand extends Command
{
    protected $signature = 'gmail:remind';

    protected $description = 'Recuerda por Telegram los correos bancarios sin responder, con botón para retomarlos';

    public function handle(BankEmailImportService $service): int
    {
        $count = $service->remindPending();

        $this->info($count === 0 ? 'Sin correos pendientes.' : "{$count} correo(s) pendiente(s) recordado(s).");

        return self::SUCCESS;
    }
}
