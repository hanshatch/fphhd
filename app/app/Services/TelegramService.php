<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Cliente mínimo de la Bot API de Telegram (solo los métodos que usa FP).
 */
class TelegramService
{
    public function sendMessage(int|string $chatId, string $text, ?array $inlineKeyboard = null): array
    {
        $params = [
            'chat_id' => $chatId,
            'text'    => $text,
        ];

        if ($inlineKeyboard !== null) {
            $params['reply_markup'] = json_encode(['inline_keyboard' => $inlineKeyboard]);
        }

        return $this->api('sendMessage', $params);
    }

    public function editMessageText(int|string $chatId, int $messageId, string $text): array
    {
        return $this->api('editMessageText', [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
            'text'       => $text,
        ]);
    }

    public function answerCallbackQuery(string $callbackQueryId): array
    {
        return $this->api('answerCallbackQuery', ['callback_query_id' => $callbackQueryId]);
    }

    public function setWebhook(string $url, string $secret): array
    {
        return $this->api('setWebhook', [
            'url'             => $url,
            'secret_token'    => $secret,
            'allowed_updates' => json_encode(['message', 'callback_query']),
        ]);
    }

    public function deleteWebhook(): array
    {
        return $this->api('deleteWebhook', []);
    }

    public function getWebhookInfo(): array
    {
        return $this->api('getWebhookInfo', []);
    }

    /** Ruta interna del archivo en los servidores de Telegram */
    public function getFilePath(string $fileId): ?string
    {
        $result = $this->api('getFile', ['file_id' => $fileId]);

        return $result['result']['file_path'] ?? null;
    }

    /** Descarga el binario de un archivo (fotos ≤ 20 MB) */
    public function downloadFile(string $filePath): ?string
    {
        $token = config('services.telegram.bot_token');

        $response = Http::timeout(30)->get("https://api.telegram.org/file/bot{$token}/{$filePath}");

        return $response->successful() ? $response->body() : null;
    }

    private function api(string $method, array $params): array
    {
        $token = config('services.telegram.bot_token');

        $response = Http::asForm()
            ->timeout(10)
            ->post("https://api.telegram.org/bot{$token}/{$method}", $params);

        return $response->json() ?? [];
    }
}
