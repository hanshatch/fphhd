<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Parser de gastos en lenguaje natural con la API de DeepSeek
 * (compatible con el formato chat/completions). Se usa como fallback
 * cuando el mensaje no empieza con un monto ("gasté 250 en tacos ayer").
 */
class DeepSeekService
{
    public function isConfigured(): bool
    {
        return (bool) config('services.deepseek.api_key');
    }

    /**
     * Devuelve ['amount' => "250.00", 'description' => ..., 'date' => "Y-m-d"|null,
     * 'category' => nombre|null] o null si no se pudo interpretar como gasto.
     */
    public function parseExpense(string $text, array $categoryNames): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $system = 'Eres el parser de gastos de una app de finanzas personales en español de México. '
            . 'Interpreta el mensaje del usuario como UN gasto y responde SOLO un objeto JSON con estas llaves: '
            . '"amount" (string decimal con 2 decimales, sin símbolo ni comas, ej. "1234.56"), '
            . '"description" (texto corto capitalizado, sin el monto ni la fecha), '
            . '"date" (fecha del gasto en formato YYYY-MM-DD, o null si no se menciona; '
            . 'interpreta expresiones como "ayer", "el lunes pasado", "el 15 de julio"), '
            . '"category" (exactamente uno de: ' . implode(', ', $categoryNames) . '; o null si ninguno aplica). '
            . 'Hoy es ' . now()->toDateString() . ' (' . now()->translatedFormat('l') . '). '
            . 'Si el mensaje NO describe un gasto, responde {"amount": null}.';

        try {
            $response = Http::withToken(config('services.deepseek.api_key'))
                ->timeout(20)
                ->post('https://api.deepseek.com/chat/completions', [
                    'model'           => config('services.deepseek.model'),
                    'messages'        => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $text],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature'     => 0,
                    'max_tokens'      => 200,
                ]);
        } catch (\Throwable $e) {
            Log::warning('DeepSeek: error de conexión', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('DeepSeek: respuesta no exitosa', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        $content = $response->json('choices.0.message.content');
        $data    = is_string($content) ? json_decode($content, true) : null;

        if (! is_array($data)) {
            return null;
        }

        $amount = parse_money($data['amount'] ?? null);

        if ($amount === null || bccomp($amount, '0.00', 2) <= 0) {
            return null;
        }

        return [
            'amount'      => $amount,
            'description' => trim((string) ($data['description'] ?? '')) ?: 'Gasto',
            'date'        => $data['date'] ?? null,
            'category'    => $data['category'] ?? null,
        ];
    }
}
