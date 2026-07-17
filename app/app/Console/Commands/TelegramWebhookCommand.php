<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:webhook
        {--remove : Elimina el webhook en lugar de registrarlo}
        {--info : Muestra el estado actual del webhook}';

    protected $description = 'Registra (o elimina) el webhook del bot de Telegram';

    public function handle(TelegramService $telegram): int
    {
        if ($this->option('info')) {
            $this->line(json_encode($telegram->getWebhookInfo(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($this->option('remove')) {
            $result = $telegram->deleteWebhook();
            $this->info($result['ok'] ?? false ? 'Webhook eliminado.' : 'Error: ' . json_encode($result));

            return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        $url    = rtrim(config('app.url'), '/') . '/telegram/webhook';
        $secret = config('services.telegram.webhook_secret');

        if (! config('services.telegram.bot_token') || ! $secret) {
            $this->error('Falta TELEGRAM_BOT_TOKEN o TELEGRAM_WEBHOOK_SECRET en el .env');

            return self::FAILURE;
        }

        $result = $telegram->setWebhook($url, $secret);

        if ($result['ok'] ?? false) {
            $this->info("Webhook registrado: {$url}");

            return self::SUCCESS;
        }

        $this->error('Error al registrar webhook: ' . json_encode($result));

        return self::FAILURE;
    }
}
