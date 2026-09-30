<?php

namespace App\Services\Mail;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\BankEmail;
use App\Models\Transaction;
use App\Services\MerchantMemoryService;
use App\Services\TelegramExpenseService;
use App\Services\TelegramService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Lee los correos nuevos de la etiqueta de Gmail, los interpreta y decide
 * qué hacer con cada uno:
 *
 *  - moneda extranjera        → Telegram con liga a la web para capturar el monto en MXN
 *  - ya registrado            → Telegram avisa y pregunta si registrar de todos modos
 *  - parece transferencia     → Telegram propone registrarla como transferencia interna
 *  - categoría ya aprendida   → se registra solo y Telegram avisa con «Deshacer»
 *  - en cualquier otro caso   → Telegram pregunta la categoría (flujo pendiente)
 */
class BankEmailImportService
{
    public function __construct(
        private MailboxReader $mailbox,
        private BankEmailParser $parser,
        private MerchantMemoryService $memory,
        private TelegramExpenseService $telegramFlow,
        private TelegramService $telegram,
    ) {}

    /**
     * Procesa los correos nuevos. Devuelve cuántos correos nuevos se guardaron.
     * Con $silent = true solo los registra como omitidos (arranque inicial:
     * evita avalancha de Telegram con correos viejos).
     */
    public function sync(int $days = 7, bool $silent = false): int
    {
        $messages = $this->mailbox->fetchRecent($days);

        if ($messages === []) {
            return 0;
        }

        $known = BankEmail::whereIn('message_uid', array_column($messages, 'uid'))->pluck('message_uid')->all();
        $new   = 0;

        // Más viejos primero, para que un depósito y su retiro se emparejen en orden
        foreach (array_reverse($messages) as $message) {
            if (in_array($message['uid'], $known, true)) {
                continue;
            }

            $this->handle($message, $silent);
            $new++;
        }

        return $new;
    }

    /** Guarda y enruta un correo. Público para poder inyectar correos en pruebas y depuración. */
    public function handle(array $message, bool $silent = false): BankEmail
    {
        $bank   = $this->parser->bankFor($message['from']);
        $parsed = $bank ? $this->parser->parse($message['from'], $message['subject'], $message['text'], $message['date']) : null;

        $email = BankEmail::create([
            'message_uid' => $message['uid'],
            'bank'        => $bank,
            'sender'      => Str::limit($message['from'], 200, ''),
            'subject'     => Str::limit($message['subject'], 300, ''),
            'received_at' => $message['date'],
            'raw_text'    => Str::limit($message['text'], 20000, ''),
            'parsed'      => $parsed,
            'auth_number' => $parsed['auth'] ?? null,
            'status'      => match (true) {
                $bank === null   => BankEmail::STATUS_IGNORED,
                $silent          => BankEmail::STATUS_SKIPPED,
                $parsed === null => BankEmail::STATUS_UNPARSED,
                default          => BankEmail::STATUS_PENDING,
            },
        ]);

        if ($silent) {
            return $email;
        }

        if ($parsed === null) {
            if ($bank !== null) {
                $this->notify('📧 Correo de ' . ucfirst($bank) . " que no supe leer:\n«" . $email->subject . "»\n\nRegístralo a mano si aplica.");
            }

            return $email;
        }

        $this->route($email);

        return $email;
    }

    // ── Enrutamiento ──────────────────────────────────────────────────

    private function route(BankEmail $email): void
    {
        $p       = $email->parsed;
        $account = $this->matchAccount($email->bank, $p['last4'] ?? null);

        if ($account) {
            $email->update(['account_id' => $account->id]);
        }

        // 1) Moneda extranjera: no sabemos el monto en pesos
        if (($p['currency'] ?? 'MXN') !== 'MXN') {
            $this->notify(
                '🌎 ' . ucfirst($email->bank) . ': ' . $p['description'] . ' por ' . number_format((float) $p['amount'], 2) . ' ' . $p['currency']
                    . "\n\nNo sé el monto en pesos. Regístralo en la web con el cargo real:",
                [[['text' => '✏️ Registrar en la web', 'url' => $this->webUrl($account, $p)]]]
            );

            return;
        }

        $type = $p['kind'] === 'income' ? 'income' : 'expense';

        // 2) Duplicado por número de autorización
        if ($p['auth'] && BankEmail::where('auth_number', $p['auth'])->where('id', '<>', $email->id)->where('status', '<>', BankEmail::STATUS_IGNORED)->exists()) {
            $email->update(['status' => BankEmail::STATUS_DUPLICATE]);

            return;
        }

        // 3) ¿Es la otra mitad de una transferencia interna?
        if ($account && ($twin = $this->findTransferTwin($account, $type, $p['amount'], $p['date']))) {
            [$from, $to] = $type === 'expense' ? [$account, $twin->account] : [$twin->account, $account];

            $this->notify(
                '🔁 ' . format_currency($p['amount']) . ' · ' . $p['description'] . ' · ' . Carbon::parse($p['date'])->translatedFormat('j M Y')
                    . "\n\nParece transferencia interna: " . $from->name . ' → ' . $to->name
                    . "\n(ya tienes registrado el otro lado: " . ($twin->description ?: 'sin descripción') . ')',
                [[
                    ['text' => '🔁 Sí, es transferencia', 'callback_data' => 'mail:xfer:' . $email->id . ':' . $twin->id],
                    ['text' => '✋ No, registrar aparte', 'callback_data' => 'mail:noxfer:' . $email->id],
                ], [
                    ['text' => '⏭ No registrar', 'callback_data' => 'mail:skip:' . $email->id],
                ]]
            );

            return;
        }

        // 4) Ya existe un movimiento igual (mismo monto, misma cuenta, ±1 día)
        if ($account && ($dup = $this->findDuplicate($account, $type, $p['amount'], $p['date']))) {
            $this->telegramFlow->enqueueFromEmail($this->pendingFor($email, $account, $type, [
                'duplicate' => [
                    'amount'      => (string) $dup->amount,
                    'description' => $dup->description ?: 'Sin descripción',
                    'account'     => $dup->account->name,
                    'date'        => $dup->date->translatedFormat('j M Y'),
                ],
            ]));

            return;
        }

        // 5) Comercio conocido: registrar solo y avisar con «Deshacer»
        if ($account && empty($p['generic']) && ($categoryId = $this->memory->suggest($p['description'], $type))) {
            $transaction = Transaction::create([
                'date'        => $p['date'],
                'type'        => $type,
                'amount'      => $p['amount'],
                'account_id'  => $account->id,
                'category_id' => $categoryId,
                'description' => $p['description'],
            ]);

            $email->update(['status' => BankEmail::STATUS_REGISTERED, 'transaction_id' => $transaction->id]);
            AuditLog::record('bank_email_autoregister', ['transaction_id' => $transaction->id, 'bank_email_id' => $email->id]);

            $transaction->load('category');

            $this->notify(
                '✅ Registrado desde correo de ' . ucfirst($email->bank) . "\n"
                    . format_currency($transaction->amount) . ' · ' . $transaction->description . "\n"
                    . $account->name . ' · ' . $transaction->category->name . ' · ' . $transaction->date->translatedFormat('j M Y'),
                [[['text' => '↩️ Deshacer', 'callback_data' => 'mail:undo:' . $email->id]]]
            );

            return;
        }

        // 6) Preguntar categoría (y cuenta si no se pudo identificar)
        $this->telegramFlow->enqueueFromEmail($this->pendingFor($email, $account, $type));
    }

    // ── Acciones desde los botones de Telegram ────────────────────────

    /** Convierte la otra mitad ya registrada en una transferencia origen → destino */
    public function confirmTransfer(BankEmail $email, Transaction $twin): ?Transaction
    {
        $p       = $email->parsed;
        $account = $email->account;

        if (! $account || ! $p || $email->status !== BankEmail::STATUS_PENDING) {
            return null;
        }

        [$from, $to] = $p['kind'] === 'income' ? [$twin->account, $account] : [$account, $twin->account];

        $twin->update([
            'type'                    => Transaction::TYPE_TRANSFER,
            'account_id'              => $from->id,
            'counterparty_account_id' => $to->id,
            'category_id'             => null,
            'source_id'               => null,
            'description'             => 'Transferencia ' . $from->name . ' → ' . $to->name,
        ]);

        $email->update(['status' => BankEmail::STATUS_REGISTERED, 'transaction_id' => $twin->id]);
        AuditLog::record('bank_email_transfer', ['transaction_id' => $twin->id, 'bank_email_id' => $email->id]);

        return $twin->fresh();
    }

    /** «No es transferencia»: sigue el flujo normal de categoría */
    public function askCategory(BankEmail $email): void
    {
        $type = ($email->parsed['kind'] ?? 'expense') === 'income' ? 'income' : 'expense';
        $this->telegramFlow->enqueueFromEmail($this->pendingFor($email, $email->account, $type));
    }

    public function undo(BankEmail $email): bool
    {
        if ($email->status !== BankEmail::STATUS_REGISTERED || ! $email->transaction) {
            return false;
        }

        $email->transaction->delete();
        $email->update(['status' => BankEmail::STATUS_SKIPPED, 'transaction_id' => null]);
        AuditLog::record('bank_email_undo', ['bank_email_id' => $email->id]);

        return true;
    }

    public function skip(BankEmail $email): void
    {
        $email->update(['status' => BankEmail::STATUS_SKIPPED]);
    }

    /** Liga un correo pendiente con la transacción que Hans terminó creando por Telegram */
    public function markRegistered(int $emailId, Transaction $transaction): void
    {
        BankEmail::where('id', $emailId)->where('status', BankEmail::STATUS_PENDING)
            ->update(['status' => BankEmail::STATUS_REGISTERED, 'transaction_id' => $transaction->id]);
    }

    public function markSkipped(int $emailId): void
    {
        BankEmail::where('id', $emailId)->where('status', BankEmail::STATUS_PENDING)
            ->update(['status' => BankEmail::STATUS_SKIPPED]);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function pendingFor(BankEmail $email, ?Account $account, string $type, array $extra = []): array
    {
        $p = $email->parsed;

        return array_merge([
            'amount'        => $p['amount'],
            'description'   => $p['description'],
            'date'          => $p['date'],
            'type'          => $type,
            'category_id'   => null,
            'account_id'    => $account?->id,
            'bank_email_id' => $email->id,
            'source_label'  => '📧 ' . ucfirst($email->bank),
        ], $extra);
    }

    /** Cuenta por terminación; si no hay, la única cuenta activa de esa institución */
    private function matchAccount(?string $bank, ?string $last4): ?Account
    {
        if ($last4) {
            $byDigits = Account::where('is_active', true)
                ->whereNotNull('bank_last4')
                ->get()
                ->first(fn (Account $a) => str_ends_with((string) $a->bank_last4, $last4) || str_ends_with($last4, (string) $a->bank_last4));

            if ($byDigits) {
                return $byDigits;
            }
        }

        if ($bank) {
            $candidates = Account::where('is_active', true)->where('institution', $bank)->get();

            if ($candidates->count() === 1) {
                return $candidates->first();
            }
        }

        return null;
    }

    /** Movimiento del tipo contrario, mismo monto, otra cuenta, ±1 día, que no sea ya transferencia */
    private function findTransferTwin(Account $account, string $type, string $amount, string $date): ?Transaction
    {
        $d = Carbon::parse($date);

        return Transaction::with('account')
            ->where('account_id', '<>', $account->id)
            ->where('type', $type === 'expense' ? Transaction::TYPE_INCOME : Transaction::TYPE_EXPENSE)
            ->where('amount', $amount)
            ->whereDate('date', '>=', $d->copy()->subDay()->toDateString())
            ->whereDate('date', '<=', $d->copy()->addDay()->toDateString())
            ->orderByDesc('id')
            ->first();
    }

    private function findDuplicate(Account $account, string $type, string $amount, string $date): ?Transaction
    {
        $d = Carbon::parse($date);

        return Transaction::with('account')
            ->where('account_id', $account->id)
            ->where('type', $type)
            ->where('amount', $amount)
            ->whereDate('date', '>=', $d->copy()->subDay()->toDateString())
            ->whereDate('date', '<=', $d->copy()->addDay()->toDateString())
            ->orderByDesc('id')
            ->first();
    }

    private function webUrl(?Account $account, array $p): string
    {
        $query = array_filter([
            'new'         => 1,
            'description' => $p['description'],
            'date'        => $p['date'],
            'type'        => $p['kind'] === 'income' ? 'income' : 'expense',
        ]);

        return $account
            ? route('accounts.show', $account) . '?' . http_build_query($query)
            : route('transactions.create') . '?' . http_build_query($query);
    }

    private function notify(string $text, ?array $keyboard = null): void
    {
        $chatId = config('services.telegram.chat_id');

        if ($chatId && config('services.telegram.bot_token')) {
            $this->telegram->sendMessage($chatId, $text, $keyboard);
        }
    }
}
