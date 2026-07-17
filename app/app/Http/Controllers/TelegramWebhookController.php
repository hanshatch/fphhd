<?php

namespace App\Http\Controllers;

use App\Services\TelegramExpenseService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramExpenseService $service): Response
    {
        $secret = config('services.telegram.webhook_secret');

        if (! $secret || ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            \Log::warning('Telegram webhook: secret inválido', ['ip' => $request->ip()]);
            abort(403);
        }

        $update = $request->all();
        $chatId = $update['message']['chat']['id']
            ?? $update['callback_query']['message']['chat']['id']
            ?? null;

        // Solo Hans: cualquier otro chat se ignora en silencio
        if ($chatId === null || (string) $chatId !== (string) config('services.telegram.chat_id')) {
            return response()->noContent();
        }

        try {
            $service->handleUpdate($update);
        } catch (\Throwable $e) {
            // Siempre 2xx: si no, Telegram reintenta el mismo update en loop
            report($e);
        }

        return response()->noContent();
    }
}
