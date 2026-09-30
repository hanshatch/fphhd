<?php

namespace App\Console\Commands;

use App\Services\Mail\BankEmailImportService;
use App\Services\Mail\MailboxReader;
use Illuminate\Console\Command;

class GmailSyncCommand extends Command
{
    protected $signature = 'gmail:sync {--days=7 : Días hacia atrás a revisar} {--silent : Solo registrar como omitidos, sin avisar (arranque inicial)}';

    protected $description = 'Lee la etiqueta de Gmail con notificaciones bancarias y registra o pregunta por Telegram';

    public function handle(BankEmailImportService $service, MailboxReader $mailbox): int
    {
        if (! $mailbox->isConfigured()) {
            $this->warn('Gmail no está configurado (GMAIL_USER / GMAIL_APP_PASSWORD).');

            return self::SUCCESS;
        }

        $new = $service->sync((int) $this->option('days'), (bool) $this->option('silent'));

        $this->info($new === 0 ? 'Sin correos nuevos.' : "{$new} correo(s) nuevo(s) procesado(s).");

        return self::SUCCESS;
    }
}
