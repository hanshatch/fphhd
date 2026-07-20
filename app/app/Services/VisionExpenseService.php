<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Lee screenshots de cargos/abonos (estados de cuenta, apps bancarias)
 * con el modelo de visión de OpenAI y devuelve los movimientos detectados.
 */
class VisionExpenseService
{
    private const MAX_ITEMS = 10;

    public function isConfigured(): bool
    {
        return (bool) config('services.openai.api_key');
    }

    /**
     * Devuelve lista de movimientos:
     * [['amount' => "129.00", 'description' => ..., 'date' => "Y-m-d"|null,
     *   'type' => 'expense'|'income', 'category' => nombre|null], ...]
     * o null si la imagen no se pudo analizar.
     */
    public function parseCharges(
        string $imageBase64,
        string $mimeType,
        array $expenseCategories,
        array $incomeCategories,
        ?string $caption = null,
    ): ?array {
        if (! $this->isConfigured()) {
            return null;
        }

        $system = 'Eres el lector de estados de cuenta de una app de finanzas personales en español de México. '
            . 'La imagen es un screenshot de cargos/abonos de una tarjeta o cuenta bancaria. '
            . 'Extrae SOLO los movimientos individuales (ignora saldos, totales, límites de crédito y anuncios) '
            . 'y responde SOLO un objeto JSON: {"charges": [{"amount", "description", "date", "type", "category"}]}. '
            . 'Reglas: "amount" string decimal positivo con 2 decimales, sin símbolo ni comas. '
            . '"description" texto corto capitalizado (comercio o concepto). '
            . '"date" fecha del movimiento YYYY-MM-DD o null si no es visible; hoy es ' . now()->toDateString() . '. '
            . '"type": "expense" si es cargo/compra/descuento, "income" si es abono/depósito/pago recibido. '
            . 'Para gastos, "category" debe ser exactamente uno de: ' . implode(', ', $expenseCategories) . '; '
            . 'para abonos, uno de: ' . implode(', ', $incomeCategories) . '; o null si ninguno aplica. '
            . 'Si no hay movimientos legibles responde {"charges": []}.';

        $userContent = [];

        if ($caption !== null && trim($caption) !== '') {
            $userContent[] = ['type' => 'text', 'text' => 'Contexto del usuario: ' . $caption];
        }

        $userContent[] = [
            'type'      => 'image_url',
            'image_url' => ['url' => "data:{$mimeType};base64,{$imageBase64}"],
        ];

        try {
            $response = Http::withToken(config('services.openai.api_key'))
                ->timeout(45)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'           => config('services.openai.model'),
                    'messages'        => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $userContent],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature'     => 0,
                    'max_tokens'      => 1500,
                ]);
        } catch (\Throwable $e) {
            Log::warning('OpenAI Vision: error de conexión', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('OpenAI Vision: respuesta no exitosa', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        $content = $response->json('choices.0.message.content');
        $data    = is_string($content) ? json_decode($content, true) : null;

        if (! is_array($data) || ! is_array($data['charges'] ?? null)) {
            return null;
        }

        $items = [];

        foreach (array_slice($data['charges'], 0, self::MAX_ITEMS) as $charge) {
            $amount = parse_money($charge['amount'] ?? null);

            if ($amount === null || bccomp($amount, '0.00', 2) <= 0) {
                continue;
            }

            $items[] = [
                'amount'      => $amount,
                'description' => trim((string) ($charge['description'] ?? '')) ?: 'Cargo',
                'date'        => $charge['date'] ?? null,
                'type'        => ($charge['type'] ?? 'expense') === 'income' ? 'income' : 'expense',
                'category'    => $charge['category'] ?? null,
            ];
        }

        return $items;
    }
}
