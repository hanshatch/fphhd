<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Registro de gastos vía Telegram: parsea "250 tacos", pregunta cuenta
 * (y categoría si no la pudo adivinar) con botones inline y crea el movimiento.
 */
class TelegramExpenseService
{
    private const PENDING_TTL_MINUTES = 30;

    public function __construct(
        private TelegramService $telegram,
        private DeepSeekService $deepseek,
        private VisionExpenseService $vision,
    ) {
    }

    public function handleUpdate(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);

            return;
        }

        if (isset($update['message']['photo'])) {
            $this->handlePhoto($update['message']);

            return;
        }

        if (isset($update['message']['text'])) {
            $this->handleMessage($update['message']);
        }
    }

    /**
     * Screenshot de cargos: descarga la foto, la analiza con visión y
     * encola los movimientos detectados para confirmarlos uno por uno.
     */
    private function handlePhoto(array $message): void
    {
        $chatId = $message['chat']['id'];

        if (! $this->vision->isConfigured()) {
            $this->telegram->sendMessage($chatId, 'El análisis de imágenes no está configurado todavía (falta la API key de visión).');

            return;
        }

        // Telegram manda varias resoluciones; la última es la más grande
        $photo    = end($message['photo']);
        $filePath = $photo ? $this->telegram->getFilePath($photo['file_id']) : null;
        $binary   = $filePath ? $this->telegram->downloadFile($filePath) : null;

        if ($binary === null) {
            $this->telegram->sendMessage($chatId, 'No pude descargar la imagen 😕 Inténtalo de nuevo.');

            return;
        }

        $expenseCats = Category::active()->ofKind(Category::KIND_EXPENSE)->orderBy('name')->pluck('name');
        $incomeCats  = Category::active()->ofKind(Category::KIND_INCOME)->orderBy('name')->pluck('name');

        $items = $this->vision->parseCharges(
            base64_encode($binary),
            str_ends_with(mb_strtolower($filePath), '.png') ? 'image/png' : 'image/jpeg',
            $expenseCats->all(),
            $incomeCats->all(),
            $message['caption'] ?? null,
        );

        if ($items === null) {
            $this->telegram->sendMessage($chatId, 'No pude analizar la imagen 😕 Inténtalo de nuevo en un momento.');

            return;
        }

        if ($items === []) {
            $this->telegram->sendMessage($chatId, 'No encontré movimientos legibles en la imagen 🤔 Prueba con un screenshot más cerrado a la lista de cargos.');

            return;
        }

        // Hans decide el tipo de cada movimiento; lo del modelo es solo pista
        $queue = array_map(function (array $item) {
            $pending = [
                'amount'        => $item['amount'],
                'description'   => Str::limit(Str::ucfirst($item['description']), 500, ''),
                'date'          => $this->sanitizeDate($item['date']),
                'type'          => $item['type'],
                'category_hint' => $item['category'],
                'category_id'   => null,
                'ask_type'      => true,
            ];

            $pending['duplicate'] = $this->findPossibleDuplicate($pending);

            return $pending;
        }, $items);

        if (count($queue) > 1) {
            $summary = collect($queue)
                ->map(fn ($q, $i) => ($i + 1) . '. ' . ($q['type'] === 'income' ? '+' : '−')
                    . format_currency($q['amount']) . ' · ' . $q['description']
                    . ($q['duplicate'] ? ' ⚠️' : ''))
                ->implode("\n");

            $this->telegram->sendMessage($chatId, '📷 Detecté ' . count($queue) . " movimientos (⚠️ = posible duplicado):\n\n" . $summary . "\n\nVamos uno por uno 👇");
        }

        $this->startPending($chatId, $queue, count($queue));
    }

    /**
     * Toma el primer item de la cola como pendiente activo. Si viene de un
     * screenshot pregunta primero el tipo (cargo/abono/interés); si no,
     * directo la cuenta.
     */
    private function startPending(int|string $chatId, array $queue, int $total): void
    {
        $pending          = array_shift($queue);
        $pending['queue'] = $queue;
        $pending['total'] = $total;

        Cache::put($this->pendingKey($chatId), $pending, now()->addMinutes(self::PENDING_TTL_MINUTES));

        $position = $total > 1 ? ($total - count($queue)) . "/{$total} · " : '';

        if (! empty($pending['duplicate'])) {
            $d = $pending['duplicate'];

            $this->telegram->sendMessage(
                $chatId,
                $position . $this->pendingSummary($pending)
                    . "\n\n⚠️ Ya tienes un movimiento parecido registrado:\n"
                    . format_currency($d['amount']) . ' · ' . $d['description'] . ' · ' . $d['account'] . ' · ' . $d['date']
                    . "\n\n¿Lo registro de todos modos?",
                [[
                    ['text' => '✅ Registrar de todos modos', 'callback_data' => 'dup:keep'],
                    ['text' => '⏭ Omitir', 'callback_data' => 'dup:skip'],
                ]]
            );

            return;
        }

        if ($pending['ask_type'] ?? false) {
            $this->telegram->sendMessage(
                $chatId,
                $position . $this->pendingSummary($pending) . "\n¿Qué es este movimiento?",
                [[
                    ['text' => '💸 Cargo',   'callback_data' => 'typ:expense'],
                    ['text' => '💰 Abono',   'callback_data' => 'typ:income'],
                ], [
                    ['text' => '📈 Interés', 'callback_data' => 'typ:interest'],
                ]]
            );

            return;
        }

        $this->telegram->sendMessage(
            $chatId,
            $position . $this->pendingSummary($pending) . "\n" . $this->accountQuestion($pending['type']),
            $this->accountKeyboard()
        );
    }

    /**
     * Busca un movimiento ya registrado con el mismo monto y fecha cercana
     * (±3 días) — típico al re-subir un screenshot del estado de cuenta.
     */
    private function findPossibleDuplicate(array $pending): ?array
    {
        $date = \Illuminate\Support\Carbon::parse($pending['date']);

        $existing = Transaction::with('account')
            ->where('amount', $pending['amount'])
            ->whereBetween('date', [
                $date->copy()->subDays(3)->toDateString(),
                $date->copy()->addDays(3)->toDateString(),
            ])
            ->orderByDesc('id')
            ->first();

        if ($existing === null) {
            return null;
        }

        return [
            'amount'      => (string) $existing->amount,
            'description' => $existing->description ?: 'Sin descripción',
            'account'     => $existing->account->name,
            'date'        => $existing->date->translatedFormat('j M Y'),
        ];
    }

    private function accountQuestion(string $type): string
    {
        return match ($type) {
            'income'   => '¿A qué cuenta se abona?',
            'interest' => '¿En qué cuenta se generó?',
            default    => '¿De qué cuenta o tarjeta se descuenta?',
        };
    }

    private function handleMessage(array $message): void
    {
        $chatId = $message['chat']['id'];
        $text   = trim($message['text']);

        if ($text === '' || str_starts_with($text, '/') || in_array(mb_strtolower($text), ['ayuda', 'help'])) {
            $this->telegram->sendMessage($chatId, $this->helpText());

            return;
        }

        $parsed = $this->parseExpense($text);

        if ($parsed !== null) {
            [$amount, $description, $date] = $parsed;
            $categoryId = $this->guessCategoryId($description);
        } elseif (($llm = $this->llmParse($text)) !== null) {
            [$amount, $description, $date, $categoryId] = $llm;
        } else {
            $this->telegram->sendMessage(
                $chatId,
                "No entendí el gasto 🤔\n\n" . $this->helpText()
            );

            return;
        }

        $this->startPending($chatId, [[
            'amount'      => $amount,
            'description' => $description,
            'date'        => $date,
            'type'        => 'expense',
            'category_id' => $categoryId,
        ]], 1);
    }

    private function handleCallback(array $callback): void
    {
        $chatId    = $callback['message']['chat']['id'];
        $messageId = $callback['message']['message_id'];
        $data      = $callback['data'] ?? '';

        $this->telegram->answerCallbackQuery($callback['id']);

        $pending = Cache::get($this->pendingKey($chatId));

        if ($pending === null) {
            $this->telegram->editMessageText($chatId, $messageId, '⏰ Este registro expiró. Mándame el gasto de nuevo.');

            return;
        }

        [$action, $id] = array_pad(explode(':', $data, 2), 2, null);

        // Resolución de posible duplicado: registrar u omitir
        if ($action === 'dup' && in_array($id, ['keep', 'skip'], true)) {
            if ($id === 'skip') {
                $queue = $pending['queue'] ?? [];
                $total = $pending['total'] ?? 1;

                Cache::forget($this->pendingKey($chatId));

                $this->telegram->editMessageText($chatId, $messageId, '⏭ ' . $this->pendingSummary($pending) . ' — omitido (ya estaba registrado).');

                if ($queue !== []) {
                    $this->startPending($chatId, $queue, $total);
                }

                return;
            }

            unset($pending['duplicate']);
            Cache::put($this->pendingKey($chatId), $pending, now()->addMinutes(self::PENDING_TTL_MINUTES));

            $this->telegram->editMessageText($chatId, $messageId, $this->pendingSummary($pending));

            if ($pending['ask_type'] ?? false) {
                $this->telegram->sendMessage($chatId, '¿Qué es este movimiento?', [[
                    ['text' => '💸 Cargo', 'callback_data' => 'typ:expense'],
                    ['text' => '💰 Abono', 'callback_data' => 'typ:income'],
                ], [
                    ['text' => '📈 Interés', 'callback_data' => 'typ:interest'],
                ]]);
            } else {
                $this->telegram->sendMessage($chatId, $this->accountQuestion($pending['type'] ?? 'expense'), $this->accountKeyboard());
            }

            return;
        }

        // Hans define el tipo del movimiento detectado en el screenshot
        if ($action === 'typ' && in_array($id, ['expense', 'income', 'interest'], true)) {
            $pending['type'] = $id;
            unset($pending['ask_type']);

            // Con el tipo ya definido, empatar/adivinar la categoría en ese kind
            $pending['category_id'] = $id === 'interest' ? null
                : ($this->matchCategoryId($pending['category_hint'] ?? null, $id)
                    ?? $this->guessCategoryId($pending['description'], $id));

            Cache::put($this->pendingKey($chatId), $pending, now()->addMinutes(self::PENDING_TTL_MINUTES));

            $this->telegram->editMessageText($chatId, $messageId, $this->pendingSummary($pending));
            $this->telegram->sendMessage($chatId, $this->accountQuestion($id), $this->accountKeyboard());

            return;
        }

        if ($action === 'acc' && ctype_digit((string) $id)) {
            $pending['account_id'] = (int) $id;

            // Interés no lleva categoría; con categoría resuelta se guarda directo
            if (($pending['type'] ?? 'expense') === 'interest' || $pending['category_id'] !== null) {
                $this->storeExpense($chatId, $messageId, $pending);

                return;
            }

            Cache::put($this->pendingKey($chatId), $pending, now()->addMinutes(self::PENDING_TTL_MINUTES));

            $this->telegram->editMessageText($chatId, $messageId, $this->pendingSummary($pending));
            $this->telegram->sendMessage($chatId, '¿Qué categoría?', $this->categoryKeyboard($pending['type'] ?? 'expense'));

            return;
        }

        if ($action === 'cat' && ctype_digit((string) $id) && isset($pending['account_id'])) {
            $pending['category_id'] = (int) $id;
            $this->storeExpense($chatId, $messageId, $pending);
        }
    }

    private function storeExpense(int|string $chatId, int $messageId, array $pending): void
    {
        $type = match ($pending['type'] ?? 'expense') {
            'income'   => Transaction::TYPE_INCOME,
            'interest' => Transaction::TYPE_INTEREST,
            default    => Transaction::TYPE_EXPENSE,
        };

        $transaction = Transaction::create([
            'date'        => $pending['date'] ?? now()->toDateString(),
            'type'        => $type,
            'amount'      => $pending['amount'],
            'account_id'  => $pending['account_id'],
            'category_id' => $pending['category_id'],
            'description' => $pending['description'],
        ]);

        Cache::forget($this->pendingKey($chatId));

        AuditLog::record('telegram_expense', ['transaction_id' => $transaction->id]);

        $transaction->load(['account', 'category']);

        $this->telegram->editMessageText(
            $chatId,
            $messageId,
            '✅ ' . match ($type) {
                Transaction::TYPE_INCOME   => 'Abono',
                Transaction::TYPE_INTEREST => 'Interés',
                default                    => 'Gasto',
            } . " registrado\n"
                . format_currency($transaction->amount) . ' · ' . $transaction->description . "\n"
                . $transaction->account->name
                . ($transaction->category ? ' · ' . $transaction->category->name : '')
                . ' · ' . $transaction->date->translatedFormat('j M Y')
        );

        // Si venían más movimientos del screenshot, seguir con el siguiente
        if (! empty($pending['queue'])) {
            $this->startPending($chatId, $pending['queue'], $pending['total'] ?? count($pending['queue']) + 1);
        }
    }

    /**
     * Empata el nombre de categoría que devolvió el modelo contra las
     * categorías reales del tipo correspondiente (sin acentos/mayúsculas).
     */
    private function matchCategoryId(?string $name, string $type): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $kind = $type === 'income' ? Category::KIND_INCOME : Category::KIND_EXPENSE;

        return Category::active()->ofKind($kind)->get()
            ->first(fn (Category $c) => $this->normalize($c->name) === $this->normalize($name))
            ?->id;
    }

    /**
     * Extrae [monto, descripción, fecha] de textos como "250 tacos",
     * "$1,234.56 super ayer", "180 uber 15/07".
     * Devuelve null si el texto no empieza con un monto válido.
     */
    private function parseExpense(string $text): ?array
    {
        if (! preg_match('/^\$?\s*([\d,]*\.?\d+)\s*(.*)$/su', $text, $matches)) {
            return null;
        }

        $amount = parse_money($matches[1]);

        if ($amount === null || bccomp($amount, '0.00', 2) <= 0) {
            return null;
        }

        [$date, $rest] = $this->extractDate(trim($matches[2]));

        $description = $rest !== '' ? Str::ucfirst($rest) : 'Gasto';

        return [$amount, Str::limit($description, 500, ''), $date];
    }

    /**
     * Busca una fecha en el texto ("hoy", "ayer", "antier", "15/07", "15/07/2026")
     * y la quita de la descripción. Valida que exista y no sea futura;
     * sin fecha (o con una inválida) se registra con la de hoy.
     */
    private function extractDate(string $text): array
    {
        $today = now()->startOfDay();

        $relative = ['hoy' => 0, 'ayer' => 1, 'antier' => 2, 'anteayer' => 2];

        foreach ($relative as $word => $daysAgo) {
            $pattern = '/(?:^|\s)' . $word . '(?:\s|$)/iu';

            if (preg_match($pattern, $text)) {
                $clean = trim(preg_replace('/\s+/u', ' ', preg_replace($pattern, ' ', $text)));

                return [$today->copy()->subDays($daysAgo)->toDateString(), $clean];
            }
        }

        if (preg_match('/(?:^|\s)(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?(?:\s|$)/u', $text, $m, PREG_OFFSET_CAPTURE)) {
            $day   = (int) $m[1][0];
            $month = (int) $m[2][0];
            $year  = isset($m[3]) && $m[3][0] !== '' ? (int) $m[3][0] : (int) $today->year;

            if ($year < 100) {
                $year += 2000;
            }

            if (checkdate($month, $day, $year)) {
                $date = $today->copy()->setDate($year, $month, $day);

                // Sin año explícito, una fecha futura se asume del año pasado
                if ((! isset($m[3]) || $m[3][0] === '') && $date->gt($today)) {
                    $date->subYear();
                }

                if ($date->lte($today)) {
                    $clean = trim(preg_replace('/\s+/u', ' ', substr_replace($text, ' ', $m[0][1], strlen($m[0][0]))));

                    return [$date->toDateString(), $clean];
                }
            }
        }

        return [$today->toDateString(), $text];
    }

    /**
     * Fallback con DeepSeek para mensajes en lenguaje natural
     * ("gasté 250 en tacos ayer"). Devuelve [monto, descripción, fecha,
     * category_id] o null si no está configurado o no entendió el gasto.
     */
    private function llmParse(string $text): ?array
    {
        if (! $this->deepseek->isConfigured()) {
            return null;
        }

        $categories = Category::active()->ofKind(Category::KIND_EXPENSE)->get();

        $result = $this->deepseek->parseExpense($text, $categories->pluck('name')->all());

        if ($result === null) {
            return null;
        }

        $categoryId = null;

        if ($result['category'] !== null) {
            $match = $categories->first(
                fn (Category $c) => $this->normalize($c->name) === $this->normalize((string) $result['category'])
            );
            $categoryId = $match?->id;
        }

        return [
            $result['amount'],
            Str::limit(Str::ucfirst($result['description']), 500, ''),
            $this->sanitizeDate($result['date']),
            $categoryId,
        ];
    }

    /**
     * Valida una fecha del LLM: parseable, no futura y no más vieja de 2 años;
     * si no cumple, se registra con la de hoy.
     */
    private function sanitizeDate(?string $value): string
    {
        $today = now()->startOfDay();

        if ($value !== null) {
            try {
                $date = \Illuminate\Support\Carbon::parse($value)->startOfDay();

                if ($date->lte($today) && $date->gte($today->copy()->subYears(2))) {
                    return $date->toDateString();
                }
            } catch (\Throwable) {
                // fecha ilegible → hoy
            }
        }

        return $today->toDateString();
    }

    private function pendingSummary(array $pending): string
    {
        $date  = \Illuminate\Support\Carbon::parse($pending['date']);
        $emoji = match ($pending['type'] ?? 'expense') {
            'income'   => '💰',
            'interest' => '📈',
            default    => '💸',
        };

        return $emoji . ' ' . format_currency($pending['amount'])
            . ' · ' . $pending['description']
            . ' · ' . $date->translatedFormat('j M Y');
    }

    /**
     * Busca una categoría de gasto cuyo nombre aparezca en la descripción
     * (sin acentos ni mayúsculas). Si hay varias, gana el nombre más largo.
     */
    private function guessCategoryId(string $description, string $type = 'expense'): ?int
    {
        $haystack = $this->normalize($description);
        $bestId   = null;
        $bestLen  = 0;

        $categories = Category::active()
            ->ofKind($type === 'income' ? Category::KIND_INCOME : Category::KIND_EXPENSE)
            ->get();

        foreach ($categories as $category) {
            $needle = $this->normalize($category->name);

            if ($needle !== '' && str_contains($haystack, $needle) && mb_strlen($needle) > $bestLen) {
                $bestId  = $category->id;
                $bestLen = mb_strlen($needle);
            }
        }

        return $bestId;
    }

    private function accountKeyboard(): array
    {
        $buttons = Account::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account) => [
                'text'          => $account->displayLabel(),
                'callback_data' => 'acc:' . $account->id,
            ]);

        return array_chunk($buttons->all(), 2);
    }

    private function categoryKeyboard(string $type = 'expense'): array
    {
        $buttons = Category::active()
            ->ofKind($type === 'income' ? Category::KIND_INCOME : Category::KIND_EXPENSE)
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category) => [
                'text'          => $category->name,
                'callback_data' => 'cat:' . $category->id,
            ]);

        return array_chunk($buttons->all(), 2);
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(Str::ascii($value));
    }

    private function pendingKey(int|string $chatId): string
    {
        return 'telegram:pending:' . $chatId;
    }

    private function helpText(): string
    {
        return "Mándame un gasto así:\n\n"
            . "250 tacos\n"
            . "1,234.56 super soriana\n"
            . "180 uber ayer\n"
            . "90 café 15/07\n\n"
            . "Sin fecha se registra hoy. Yo te pregunto de qué cuenta salió y, si no la adivino, la categoría.\n\n"
            . '📷 También puedes mandarme un screenshot de los cargos de tu tarjeta y los registro uno por uno.';
    }
}
