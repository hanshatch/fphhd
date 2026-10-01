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
    /**
     * Entrada desde el lector de correos bancarios: si Hans está a media
     * captura se encola detrás; si no, arranca el flujo de inmediato.
     * El pendiente trae cuenta resuelta (si se pudo) y bank_email_id.
     */
    public function enqueueFromEmail(array $pending): void
    {
        $chatId = config('services.telegram.chat_id');

        if (! $chatId || ! config('services.telegram.bot_token')) {
            return;
        }

        $current = Cache::get($this->pendingKey($chatId));

        if ($current !== null) {
            $current['queue'][] = $pending;
            $current['total']   = ($current['total'] ?? 1) + 1;
            Cache::put($this->pendingKey($chatId), $current, now()->addMinutes(self::PENDING_TTL_MINUTES));

            return;
        }

        $this->startPending($chatId, [$pending], 1);
    }

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
                $this->typeKeyboard()
            );

            return;
        }

        // Cuenta ya resuelta (correo bancario): directo a la categoría
        if (! empty($pending['account_id'])) {
            $account = Account::find($pending['account_id']);
            $type    = $pending['type'] ?? 'expense';

            $pending['category_suggested'] = $this->memory()->suggest($pending['description'], $type)
                ?? $this->guessCategoryId($pending['description'], $type);
            Cache::put($this->pendingKey($chatId), $pending, now()->addMinutes(self::PENDING_TTL_MINUTES));

            $this->telegram->sendMessage(
                $chatId,
                $position . $this->pendingSummary($pending) . "\n" . ($account?->name ?? 'Cuenta') . "\n¿Qué categoría?",
                $this->categoryRootKeyboard($type, $pending['category_suggested'])
            );

            return;
        }

        $this->telegram->sendMessage(
            $chatId,
            $position . $this->pendingSummary($pending) . "\n" . $this->accountQuestion($pending['type']),
            $this->accountKeyboard()
        );
    }

    private function memory(): \App\Services\MerchantMemoryService
    {
        return app(\App\Services\MerchantMemoryService::class);
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

    /**
     * Botones de la notificación de cargo recurrente: aplicar con el monto
     * configurado (fecha de hoy) u omitir (se re-notifica al día siguiente).
     */
    private function handleRecurringCallback(int|string $chatId, int $messageId, string $data): void
    {
        [, $action, $id] = array_pad(explode(':', $data, 3), 3, null);

        $charge = ctype_digit((string) $id)
            ? \App\Models\RecurringCharge::with('account', 'category')->find((int) $id)
            : null;

        if ($charge === null) {
            $this->telegram->editMessageText($chatId, $messageId, 'ℹ️ Este cargo recurrente ya no existe.');

            return;
        }

        if ($action === 'skip') {
            $this->telegram->editMessageText(
                $chatId,
                $messageId,
                '⏭ «' . $charge->name . '» pospuesto — te lo recuerdo mañana.'
            );

            return;
        }

        if ($action === 'apply') {
            // Evitar doble aplicación si el botón se pica dos veces
            if (! $charge->is_active || $charge->next_application_date->gt(now()->startOfDay())) {
                $this->telegram->editMessageText($chatId, $messageId, 'ℹ️ «' . $charge->name . '» ya no está pendiente de aplicar.');

                return;
            }

            $transaction = app(RecurringChargeService::class)->applyCharge($charge, null, now()->toDateString());

            AuditLog::record('telegram_recurring_apply', [
                'transaction_id'      => $transaction->id,
                'recurring_charge_id' => $charge->id,
            ]);

            $this->telegram->editMessageText(
                $chatId,
                $messageId,
                "✅ Cargo aplicado\n"
                    . format_currency($transaction->amount) . ' · ' . $transaction->description . "\n"
                    . $charge->account->displayLabel() . ' · ' . $transaction->date->translatedFormat('j M Y')
            );
        }
    }

    /**
     * Botones de las notificaciones de correo bancario:
     *  mail:xfer:<email>:<tx>  → el movimiento ya registrado se vuelve transferencia
     *  mail:noxfer:<email>     → no es transferencia, preguntar categoría
     *  mail:rec:<email>:<cargo> → aplicar el recurrente con el monto real
     *  mail:adj:<email>:<tx>   → ajustar al monto real un recurrente ya aplicado
     *  mail:ask:<email>        → no es ese recurrente, preguntar categoría
     *  mail:undo:<email>       → borrar lo que se registró solo
     *  mail:skip:<email>       → no registrar
     */
    private function handleMailCallback(int|string $chatId, int $messageId, string $data): void
    {
        [, $action, $emailId, $twinId] = array_pad(explode(':', $data, 4), 4, null);

        $email   = ctype_digit((string) $emailId) ? \App\Models\BankEmail::with('account')->find((int) $emailId) : null;
        $service = app(\App\Services\Mail\BankEmailImportService::class);

        if ($email === null) {
            $this->telegram->editMessageText($chatId, $messageId, 'ℹ️ Ese correo ya no está pendiente.');

            return;
        }

        if ($action === 'xfer') {
            $twin = ctype_digit((string) $twinId) ? Transaction::with('account')->find((int) $twinId) : null;
            $tx   = $twin ? $service->confirmTransfer($email, $twin) : null;

            $this->telegram->editMessageText($chatId, $messageId, $tx
                ? "🔁 Transferencia registrada\n" . format_currency($tx->amount) . ' · ' . $tx->description . ' · ' . $tx->date->translatedFormat('j M Y')
                : 'ℹ️ No pude convertirlo en transferencia; regístralo a mano.');

            return;
        }

        if ($action === 'xacc') {
            $other = ctype_digit((string) $twinId) ? Account::find((int) $twinId) : null;
            $tx    = $other ? $service->registerOwnTransfer($email, $other) : null;

            $this->telegram->editMessageText($chatId, $messageId, $tx
                ? "🔁 Transferencia registrada\n" . format_currency($tx->amount) . ' · ' . $tx->description . ' · ' . $tx->date->translatedFormat('j M Y')
                : 'ℹ️ Ese correo ya no está pendiente.');

            return;
        }

        if ($action === 'rec') {
            $charge = ctype_digit((string) $twinId) ? \App\Models\RecurringCharge::find((int) $twinId) : null;
            $tx     = $charge ? $service->applyRecurring($email, $charge) : null;

            $this->telegram->editMessageText($chatId, $messageId, $tx
                ? "✅ Cargo recurrente aplicado\n" . format_currency($tx->amount) . ' · ' . $tx->description . ' · ' . $tx->date->translatedFormat('j M Y')
                    . "\nPróximo: " . $charge->fresh()->next_application_date->translatedFormat('j M Y')
                : 'ℹ️ Ese cargo recurrente ya no está pendiente.');

            return;
        }

        if ($action === 'adj') {
            $target = ctype_digit((string) $twinId) ? Transaction::find((int) $twinId) : null;
            $tx     = $target ? $service->adjustTransaction($email, $target) : null;

            $this->telegram->editMessageText($chatId, $messageId, $tx
                ? "✏️ Ajustado al monto real\n" . format_currency($tx->amount) . ' · ' . $tx->description . ' · ' . $tx->date->translatedFormat('j M Y')
                : 'ℹ️ No pude ajustar ese movimiento.');

            return;
        }

        if ($action === 'noxfer' || $action === 'ask') {
            $this->telegram->editMessageText($chatId, $messageId, '✋ Ok, lo registramos aparte.');
            $service->askCategory($email);

            return;
        }

        if ($action === 'undo') {
            $this->telegram->editMessageText($chatId, $messageId, $service->undo($email)
                ? '↩️ Deshecho: el movimiento se eliminó.'
                : 'ℹ️ Ya no había nada que deshacer.');

            return;
        }

        if ($action === 'skip') {
            $service->skip($email);
            $this->telegram->editMessageText($chatId, $messageId, '⏭ No registrado.');
        }
    }

    /** Descarta el pendiente actual y continúa con la cola si hay más */
    private function skipPending(int|string $chatId, int $messageId, array $pending, string $reason): void
    {
        $queue = $pending['queue'] ?? [];
        $total = $pending['total'] ?? 1;

        Cache::forget($this->pendingKey($chatId));

        if (! empty($pending['bank_email_id'])) {
            app(\App\Services\Mail\BankEmailImportService::class)->markSkipped((int) $pending['bank_email_id']);
        }

        $this->telegram->editMessageText($chatId, $messageId, '⏭ ' . $this->pendingSummary($pending) . " — {$reason}.");

        if ($queue !== []) {
            $this->startPending($chatId, $queue, $total);
        }
    }

    private function typeKeyboard(): array
    {
        return [[
            ['text' => '💸 Cargo', 'callback_data' => 'typ:expense'],
            ['text' => '💰 Abono', 'callback_data' => 'typ:income'],
        ], [
            ['text' => '📈 Interés', 'callback_data' => 'typ:interest'],
            ['text' => '⏭ No registrar', 'callback_data' => 'skp:1'],
        ], [
            ['text' => '✏️ Editar concepto', 'callback_data' => 'pdesc:1'],
        ]];
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

        if ($text !== '' && ! str_starts_with($text, '/') && $this->consumeDescriptionEdit($chatId, $text)) {
            return;
        }

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

        // Confirmación de cargos recurrentes (independiente del flujo pendiente)
        if (str_starts_with($callback['data'] ?? '', 'rec:')) {
            $this->handleRecurringCallback($chatId, $messageId, $callback['data']);

            return;
        }

        // Corregir un movimiento ya registrado: categoría o concepto
        if (str_starts_with($callback['data'] ?? '', 'ed:')) {
            $this->handleEditCallback($chatId, $messageId, $callback['data']);

            return;
        }

        // Botones de correos bancarios (transferencia / deshacer / omitir)
        if (str_starts_with($callback['data'] ?? '', 'mail:')) {
            $this->handleMailCallback($chatId, $messageId, $callback['data']);

            return;
        }

        $pending = Cache::get($this->pendingKey($chatId));

        if ($pending === null) {
            $this->telegram->editMessageText($chatId, $messageId, '⏰ Este registro expiró. Mándame el gasto de nuevo.');

            return;
        }

        [$action, $id] = array_pad(explode(':', $data, 2), 2, null);

        // Editar / complementar el concepto antes de registrar
        if ($action === 'pdesc') {
            Cache::put($this->editKey($chatId), ['tx_id' => null], now()->addMinutes(self::PENDING_TTL_MINUTES));
            $this->telegram->sendMessage($chatId, $this->descriptionPrompt($pending['description'] ?? ''));

            return;
        }

        // No registrar el movimiento actual (en cualquier paso)
        if ($action === 'skp') {
            $this->skipPending($chatId, $messageId, $pending, 'no registrado');

            return;
        }

        // Resolución de posible duplicado: registrar u omitir
        if ($action === 'dup' && in_array($id, ['keep', 'skip'], true)) {
            if ($id === 'skip') {
                $this->skipPending($chatId, $messageId, $pending, 'omitido, ya estaba registrado');

                return;
            }

            unset($pending['duplicate']);
            Cache::put($this->pendingKey($chatId), $pending, now()->addMinutes(self::PENDING_TTL_MINUTES));

            $this->telegram->editMessageText($chatId, $messageId, $this->pendingSummary($pending));

            if ($pending['ask_type'] ?? false) {
                $this->telegram->sendMessage($chatId, '¿Qué es este movimiento?', $this->typeKeyboard());
            } else {
                $this->telegram->sendMessage($chatId, $this->accountQuestion($pending['type'] ?? 'expense'), $this->accountKeyboard());
            }

            return;
        }

        // Hans define el tipo del movimiento detectado en el screenshot
        if ($action === 'typ' && in_array($id, ['expense', 'income', 'interest'], true)) {
            $pending['type'] = $id;
            unset($pending['ask_type']);

            // La categoría SIEMPRE se pregunta; el empate solo queda como sugerencia
            $pending['category_id'] = null;
            $pending['category_suggested'] = $id === 'interest' ? null
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
            $this->telegram->sendMessage(
                $chatId,
                '¿Qué categoría?',
                $this->categoryRootKeyboard($pending['type'] ?? 'expense', $pending['category_suggested'] ?? null)
            );

            return;
        }

        // Navegación del teclado de categorías: abrir grupo / volver a grupos
        if ($action === 'catg' && ctype_digit((string) $id)) {
            $root = Category::active()->find((int) $id);

            if ($root !== null) {
                $this->telegram->editMessageText($chatId, $messageId, '¿Qué categoría? · ' . $root->name, $this->categoryChildrenKeyboard($root));
            }

            return;
        }

        if ($action === 'catb') {
            $this->telegram->editMessageText(
                $chatId,
                $messageId,
                '¿Qué categoría?',
                $this->categoryRootKeyboard($pending['type'] ?? 'expense', $pending['category_suggested'] ?? null)
            );

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

        if (! empty($pending['bank_email_id'])) {
            app(\App\Services\Mail\BankEmailImportService::class)->markRegistered((int) $pending['bank_email_id'], $transaction);
        }

        $this->telegram->editMessageText($chatId, $messageId, $this->transactionSummary($transaction), $this->correctionKeyboard($transaction));

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

        return (isset($pending['source_label']) ? $pending['source_label'] . ' · ' : '')
            . $emoji . ' ' . format_currency($pending['amount'])
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
            ->get()
            ->sortBy(fn (Account $a) => mb_strtolower($a->institutionLabel() . '·' . $a->name))
            ->values()
            ->map(fn (Account $account) => [
                'text'          => $account->displayLabel(),
                'callback_data' => 'acc:' . $account->id,
            ]);

        $rows   = array_chunk($buttons->all(), 2);
        $rows[] = [
            ['text' => '✏️ Editar concepto', 'callback_data' => 'pdesc:1'],
            ['text' => '⏭ No registrar', 'callback_data' => 'skp:1'],
        ];

        return $rows;
    }

    /**
     * Nivel 1 del selector de categorías: sugerencia (si hay) + grupos raíz.
     * Los grupos con hijas abren submenú (catg:); las raíces sin hijas
     * se eligen directo (cat:).
     */
    private function categoryRootKeyboard(string $type = 'expense', ?int $suggestedId = null, ?int $editTx = null): array
    {
        $cb   = $this->categoryCallbacks($editTx);
        $rows = [];

        if ($suggestedId !== null && ($suggested = Category::active()->find($suggestedId))) {
            $label = $editTx ? '✓ Actual: ' : '⭐ Sugerida: ';
            $rows[] = [['text' => $label . $suggested->name, 'callback_data' => $cb['cat']($suggested->id)]];
        }

        $roots = Category::active()
            ->ofKind($type === 'income' ? Category::KIND_INCOME : Category::KIND_EXPENSE)
            ->whereNull('parent_id')
            ->withCount('children')
            ->orderBy('name')
            ->get();

        $buttons = $roots->map(fn (Category $root) => $root->children_count > 0
            ? ['text' => $root->name . ' ▸', 'callback_data' => $cb['catg']($root->id)]
            : ['text' => $root->name, 'callback_data' => $cb['cat']($root->id)]);

        $rows   = array_merge($rows, array_chunk($buttons->all(), 2));
        $rows[] = $cb['footer'];

        return $rows;
    }

    /** Nivel 2: subcategorías de un grupo + usar el grupo general + volver */
    private function categoryChildrenKeyboard(Category $root, ?int $editTx = null): array
    {
        $cb = $this->categoryCallbacks($editTx);

        $buttons = $root->children()
            ->where('is_archived', false)
            ->orderBy('name')
            ->get()
            ->map(fn (Category $child) => [
                'text'          => $child->name,
                'callback_data' => $cb['cat']($child->id),
            ]);

        $rows   = array_chunk($buttons->all(), 2);
        $rows[] = [['text' => '📁 ' . $root->name . ' (general)', 'callback_data' => $cb['cat']($root->id)]];
        $rows[] = array_merge([['text' => '◀️ Volver', 'callback_data' => $cb['back']]], $editTx ? [] : [['text' => '⏭ No registrar', 'callback_data' => 'skp:1']]);

        if ($editTx) {
            $rows[] = $cb['footer'];
        }

        return $rows;
    }

    /**
     * Callbacks del selector de categorías. Al registrar (pendiente) usan
     * cat:/catg:/catb:; al corregir un movimiento ya guardado, ed:…:<tx>.
     */
    private function categoryCallbacks(?int $editTx): array
    {
        if ($editTx) {
            return [
                'cat'    => fn (int $id) => "ed:set:{$editTx}:{$id}",
                'catg'   => fn (int $id) => "ed:catg:{$editTx}:{$id}",
                'back'   => "ed:catb:{$editTx}",
                'footer' => [['text' => '✖️ Dejar como está', 'callback_data' => "ed:x:{$editTx}"]],
            ];
        }

        return [
            'cat'    => fn (int $id) => 'cat:' . $id,
            'catg'   => fn (int $id) => 'catg:' . $id,
            'back'   => 'catb:1',
            'footer' => [
                ['text' => '✏️ Editar concepto', 'callback_data' => 'pdesc:1'],
                ['text' => '⏭ No registrar', 'callback_data' => 'skp:1'],
            ],
        ];
    }

    // ── Corrección de concepto y categoría ───────────────────────────

    /** Texto de confirmación de un movimiento registrado */
    public function transactionSummary(Transaction $tx, string $title = ''): string
    {
        $tx->loadMissing(['account', 'category']);

        $title = $title ?: '✅ ' . match ($tx->type) {
            Transaction::TYPE_INCOME   => 'Abono',
            Transaction::TYPE_INTEREST => 'Interés',
            default                    => 'Gasto',
        } . ' registrado';

        return $title . "\n"
            . format_currency($tx->amount) . ' · ' . $tx->description . "\n"
            . $tx->account->name
            . ($tx->category ? ' · ' . $tx->category->name : '')
            . ' · ' . $tx->date->translatedFormat('j M Y');
    }

    /** Botones para corregir un movimiento ya registrado */
    public function correctionKeyboard(Transaction $tx, array $extraRow = []): array
    {
        $row = [];

        if (in_array($tx->type, [Transaction::TYPE_EXPENSE, Transaction::TYPE_INCOME], true)) {
            $row[] = ['text' => '🏷 Cambiar categoría', 'callback_data' => 'ed:cat:' . $tx->id];
        }

        $row[] = ['text' => '✏️ Editar concepto', 'callback_data' => 'ed:desc:' . $tx->id];

        return array_values(array_filter([$row, $extraRow]));
    }

    /**
     * ed:cat:<tx>           → selector de categorías para corregir
     * ed:catg:<tx>:<root>   → subcategorías de un grupo
     * ed:catb:<tx>          → volver a grupos
     * ed:set:<tx>:<cat>     → guardar la categoría nueva
     * ed:desc:<tx>          → esperar el nuevo concepto por texto
     * ed:x:<tx>             → cancelar y dejar el resumen
     */
    private function handleEditCallback(int|string $chatId, int $messageId, string $data): void
    {
        [, $action, $txId, $id] = array_pad(explode(':', $data, 4), 4, null);

        $tx = ctype_digit((string) $txId) ? Transaction::with(['account', 'category'])->find((int) $txId) : null;

        if ($tx === null) {
            $this->telegram->editMessageText($chatId, $messageId, 'ℹ️ Ese movimiento ya no existe.');

            return;
        }

        $type = $tx->type === Transaction::TYPE_INCOME ? 'income' : 'expense';

        switch ($action) {
            case 'cat':
            case 'catb':
                $this->telegram->editMessageText($chatId, $messageId, $this->transactionSummary($tx, '🏷 Cambiar categoría') . "\n¿Qué categoría?",
                    $this->categoryRootKeyboard($type, $tx->category_id, $tx->id));
                break;

            case 'catg':
                $root = ctype_digit((string) $id) ? Category::active()->find((int) $id) : null;
                if ($root) {
                    $this->telegram->editMessageText($chatId, $messageId, '¿Qué categoría? · ' . $root->name, $this->categoryChildrenKeyboard($root, $tx->id));
                }
                break;

            case 'set':
                $category = ctype_digit((string) $id) ? Category::active()->find((int) $id) : null;
                if ($category) {
                    $tx->update(['category_id' => $category->id]);
                    AuditLog::record('telegram_edit_category', ['transaction_id' => $tx->id, 'category_id' => $category->id]);
                    $tx->setRelation('category', $category);
                }
                $this->telegram->editMessageText($chatId, $messageId, $this->transactionSummary($tx, '✅ Categoría actualizada'), $this->correctionKeyboard($tx));
                break;

            case 'desc':
                Cache::put($this->editKey($chatId), ['tx_id' => $tx->id], now()->addMinutes(self::PENDING_TTL_MINUTES));
                $this->telegram->sendMessage($chatId, $this->descriptionPrompt($tx->description ?? ''));
                break;

            default: // x
                $this->telegram->editMessageText($chatId, $messageId, $this->transactionSummary($tx), $this->correctionKeyboard($tx));
        }
    }

    private function descriptionPrompt(string $current): string
    {
        return "✏️ Escribe el concepto.\nActual: «" . $current . "»\n\n"
            . "Empieza con + para agregarlo al final (ej. «+ uniforme Vale»). Escribe «cancelar» para dejarlo igual.";
    }

    /**
     * Si hay una edición de concepto esperando, el texto recibido es el
     * concepto nuevo (o complemento con «+»). Devuelve true si lo consumió.
     */
    private function consumeDescriptionEdit(int|string $chatId, string $text): bool
    {
        $edit = Cache::get($this->editKey($chatId));

        if ($edit === null) {
            return false;
        }

        Cache::forget($this->editKey($chatId));

        if (in_array(mb_strtolower($text), ['cancelar', 'cancel'], true)) {
            $this->telegram->sendMessage($chatId, 'Ok, el concepto se queda igual.');

            return true;
        }

        $compose = function (string $current) use ($text): string {
            $new = str_starts_with($text, '+') ? trim($current . ' ' . trim(mb_substr($text, 1))) : $text;

            return Str::limit($new, 500, '');
        };

        // Movimiento ya registrado
        if (! empty($edit['tx_id'])) {
            $tx = Transaction::with(['account', 'category'])->find((int) $edit['tx_id']);

            if ($tx === null) {
                $this->telegram->sendMessage($chatId, 'ℹ️ Ese movimiento ya no existe.');

                return true;
            }

            $tx->update(['description' => $compose((string) $tx->description)]);
            AuditLog::record('telegram_edit_description', ['transaction_id' => $tx->id]);

            $this->telegram->sendMessage($chatId, $this->transactionSummary($tx, '✅ Concepto actualizado'), $this->correctionKeyboard($tx));

            return true;
        }

        // Movimiento pendiente: actualizar y volver a preguntar el paso en curso
        $pending = Cache::get($this->pendingKey($chatId));

        if ($pending === null) {
            $this->telegram->sendMessage($chatId, '⏰ Ese registro expiró. Mándame el gasto de nuevo.');

            return true;
        }

        $pending['description'] = $compose((string) ($pending['description'] ?? ''));
        Cache::put($this->pendingKey($chatId), $pending, now()->addMinutes(self::PENDING_TTL_MINUTES));

        $this->reaskPending($chatId, $pending);

        return true;
    }

    /** Repite la pregunta del paso en que va el pendiente, con el resumen actualizado */
    private function reaskPending(int|string $chatId, array $pending): void
    {
        $summary = $this->pendingSummary($pending);

        if (! empty($pending['ask_type'])) {
            $this->telegram->sendMessage($chatId, $summary . "\n¿Qué es este movimiento?", $this->typeKeyboard());

            return;
        }

        if (empty($pending['account_id'])) {
            $this->telegram->sendMessage($chatId, $summary . "\n" . $this->accountQuestion($pending['type'] ?? 'expense'), $this->accountKeyboard());

            return;
        }

        $type = $pending['type'] ?? 'expense';
        $pending['category_suggested'] = $this->memory()->suggest($pending['description'], $type)
            ?? ($pending['category_suggested'] ?? null);
        Cache::put($this->pendingKey($chatId), $pending, now()->addMinutes(self::PENDING_TTL_MINUTES));

        $this->telegram->sendMessage($chatId, $summary . "\n¿Qué categoría?", $this->categoryRootKeyboard($type, $pending['category_suggested']));
    }

    private function editKey(int|string $chatId): string
    {
        return 'telegram:edit:' . $chatId;
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
