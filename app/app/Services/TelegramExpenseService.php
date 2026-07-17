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

    public function __construct(private TelegramService $telegram)
    {
    }

    public function handleUpdate(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);

            return;
        }

        if (isset($update['message']['text'])) {
            $this->handleMessage($update['message']);
        }
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

        if ($parsed === null) {
            $this->telegram->sendMessage(
                $chatId,
                "No entendí el monto 🤔\n\n" . $this->helpText()
            );

            return;
        }

        [$amount, $description] = $parsed;

        $pending = [
            'amount'      => $amount,
            'description' => $description,
            'category_id' => $this->guessCategoryId($description),
        ];

        Cache::put($this->pendingKey($chatId), $pending, now()->addMinutes(self::PENDING_TTL_MINUTES));

        $this->telegram->sendMessage(
            $chatId,
            '💸 ' . format_currency($amount) . ' · ' . $description . "\n¿De qué cuenta?",
            $this->accountKeyboard()
        );
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

        if ($action === 'acc' && ctype_digit((string) $id)) {
            $pending['account_id'] = (int) $id;

            if ($pending['category_id'] !== null) {
                $this->storeExpense($chatId, $messageId, $pending);

                return;
            }

            Cache::put($this->pendingKey($chatId), $pending, now()->addMinutes(self::PENDING_TTL_MINUTES));

            $this->telegram->editMessageText($chatId, $messageId, '💸 ' . format_currency($pending['amount']) . ' · ' . $pending['description']);
            $this->telegram->sendMessage($chatId, '¿Qué categoría?', $this->categoryKeyboard());

            return;
        }

        if ($action === 'cat' && ctype_digit((string) $id) && isset($pending['account_id'])) {
            $pending['category_id'] = (int) $id;
            $this->storeExpense($chatId, $messageId, $pending);
        }
    }

    private function storeExpense(int|string $chatId, int $messageId, array $pending): void
    {
        $transaction = Transaction::create([
            'date'        => now()->toDateString(),
            'type'        => Transaction::TYPE_EXPENSE,
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
            "✅ Gasto registrado\n"
                . format_currency($transaction->amount) . ' · ' . $transaction->description . "\n"
                . $transaction->account->name
                . ($transaction->category ? ' · ' . $transaction->category->name : '')
                . ' · ' . $transaction->date->translatedFormat('j M Y')
        );
    }

    /**
     * Extrae [monto, descripción] de textos como "250 tacos", "$1,234.56 super".
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

        $description = trim($matches[2]) !== '' ? Str::ucfirst(trim($matches[2])) : 'Gasto';

        return [$amount, Str::limit($description, 500, '')];
    }

    /**
     * Busca una categoría de gasto cuyo nombre aparezca en la descripción
     * (sin acentos ni mayúsculas). Si hay varias, gana el nombre más largo.
     */
    private function guessCategoryId(string $description): ?int
    {
        $haystack = $this->normalize($description);
        $bestId   = null;
        $bestLen  = 0;

        $categories = Category::active()->ofKind(Category::KIND_EXPENSE)->get();

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
                'text'          => $account->name,
                'callback_data' => 'acc:' . $account->id,
            ]);

        return array_chunk($buttons->all(), 2);
    }

    private function categoryKeyboard(): array
    {
        $buttons = Category::active()
            ->ofKind(Category::KIND_EXPENSE)
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
            . "1,234.56 super soriana\n\n"
            . 'Yo te pregunto de qué cuenta salió y, si no la adivino, la categoría.';
    }
}
