<?php

namespace App\Services\Mail;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\BankEmail;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MerchantMemoryService;
use App\Services\TelegramExpenseService;
use App\Services\TelegramService;
use App\Services\TransactionMatchService;
use App\Services\RecurringMatchService;
use App\Services\RecurringChargeService;
use App\Models\RecurringCharge;
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
        private TransactionMatchService $matcher,
        private RecurringMatchService $recurring,
        private RecurringChargeService $charges,
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

        $isInfo = ($parsed['kind'] ?? null) === 'info';

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
                $bank === null || $isInfo => BankEmail::STATUS_IGNORED,
                $silent          => BankEmail::STATUS_SKIPPED,
                $parsed === null => BankEmail::STATUS_UNPARSED,
                default          => BankEmail::STATUS_PENDING,
            },
        ]);

        if ($silent || $isInfo) {
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

        $direction = $type === 'income' ? 'in' : 'out';

        // 3) Ya registrado en esta cuenta (también si fue como transferencia desde la otra cuenta)
        if ($account && ($dup = $this->matcher->existing($account, $direction, $p['amount'], $p['date'], 1))) {
            // Si ya es transferencia, este correo es la otra mitad: se cierra sin preguntar
            if ($dup->type === Transaction::TYPE_TRANSFER) {
                $email->update(['status' => BankEmail::STATUS_DUPLICATE, 'transaction_id' => $dup->id]);
                $d = $this->matcher->describe($dup, $account);
                $this->notify('🔁 ' . ucfirst($email->bank) . ' · ' . format_currency($p['amount']) . ' · ' . $p['description']
                    . "\nYa estaba registrada: " . $d['account'] . ' · ' . $d['date'] . '.');

                return;
            }

            $this->telegramFlow->enqueueFromEmail($this->pendingFor($email, $account, $type, [
                'duplicate' => $this->matcher->describe($dup, $account),
            ]));

            return;
        }

        // 4) ¿Es la otra mitad de una transferencia interna?
        if ($account && ($twin = $this->matcher->transferTwin($account, $direction, $p['amount'], $p['date'], 1))) {
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

        // 4b) Transferencia con tu propio nombre: preguntar la otra cuenta tuya
        if ($account && ($p['counterparty'] ?? null) && $this->isOwnName($p['counterparty'])) {
            $incoming = $type === 'income';
            $others   = Account::where('is_active', true)->where('id', '<>', $account->id)->get()
                ->sortBy(fn (Account $a) => mb_strtolower($a->displayLabel()))->values();
            $hinted   = $this->hintedAccounts($p['counterparty'], $others);

            $buttons = $others
                ->sortByDesc(fn (Account $a) => in_array($a->id, $hinted, true))
                ->map(fn (Account $a) => ['text' => (in_array($a->id, $hinted, true) ? '⭐ ' : '') . $a->displayLabel(), 'callback_data' => 'mail:xacc:' . $email->id . ':' . $a->id])
                ->values()->all();

            $this->notify(
                '🔁 ' . ucfirst($email->bank) . ' · ' . format_currency($p['amount']) . ' · ' . $p['description'] . ' · ' . Carbon::parse($p['date'])->translatedFormat('j M Y')
                    . "\n\nParece transferencia entre tus cuentas. " . ($incoming ? '¿Desde qué cuenta salió?' : '¿A qué cuenta llegó?'),
                array_merge(array_chunk($buttons, 2), [[
                    ['text' => '✋ No, es otro movimiento', 'callback_data' => 'mail:ask:' . $email->id],
                    ['text' => '⏭ No registrar', 'callback_data' => 'mail:skip:' . $email->id],
                ]])
            );

            return;
        }

        // 5) ¿Es un cargo recurrente? Nunca se aplica solo: se propone con botón
        if ($account && ($match = $this->recurring->assign($account, [0 => [
            'type' => $type, 'amount' => $p['amount'], 'date' => $p['date'], 'description' => $p['description'],
        ]])[0] ?? null)) {
            $charge   = $match['charge'];
            $tx       = $match['transaction'];
            $expected = (string) ($tx ? $tx->amount : $charge->amount);
            $diff     = bcsub($p['amount'], $expected, 2);
            $diffText = (bccomp($diff, '0', 2) >= 0 ? '+' : '−') . format_currency(ltrim($diff, '-'));

            $this->notify(
                '↻ ' . format_currency($p['amount']) . ' · ' . $p['description'] . ' · ' . Carbon::parse($p['date'])->translatedFormat('j M Y')
                    . "\n\nParece el cargo recurrente «" . $charge->name . '»'
                    . ($tx
                        ? "\nYa lo aplicaste con " . format_currency($expected) . ' (' . $diffText . ').'
                        : "\nEstimado " . format_currency($expected) . ' (' . $diffText . ').'),
                [[
                    $tx
                        ? ['text' => '✏️ Ajustar a ' . format_currency($p['amount']), 'callback_data' => 'mail:adj:' . $email->id . ':' . $tx->id]
                        : ['text' => '✅ Aplicar con ' . format_currency($p['amount']), 'callback_data' => 'mail:rec:' . $email->id . ':' . $charge->id],
                ], [
                    ['text' => '✋ No es ese', 'callback_data' => 'mail:ask:' . $email->id],
                    ['text' => '⏭ No registrar', 'callback_data' => 'mail:skip:' . $email->id],
                ]]
            );

            return;
        }

        // 6) Comercio conocido: registrar solo y avisar con «Deshacer»
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

            $this->notify(
                $this->telegramFlow->transactionSummary($transaction, '✅ Registrado desde correo de ' . ucfirst($email->bank)),
                $this->telegramFlow->correctionKeyboard($transaction, [['text' => '↩️ Deshacer', 'callback_data' => 'mail:undo:' . $email->id]])
            );

            return;
        }

        // 7) Preguntar categoría (y cuenta si no se pudo identificar)
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

        $twin = $this->matcher->convertToTransfer($twin, $from, $to);

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

    /** Registra la transferencia entre esta cuenta y otra cuenta propia elegida en Telegram */
    public function registerOwnTransfer(BankEmail $email, Account $other): ?Transaction
    {
        $p       = $email->parsed;
        $account = $email->account;

        if ($email->status !== BankEmail::STATUS_PENDING || ! $account || ! $p || $other->id === $account->id) {
            return null;
        }

        [$from, $to] = $p['kind'] === 'income' ? [$other, $account] : [$account, $other];

        $tx = Transaction::create([
            'date'                    => $p['date'],
            'type'                    => Transaction::TYPE_TRANSFER,
            'amount'                  => $p['amount'],
            'account_id'              => $from->id,
            'counterparty_account_id' => $to->id,
            'description'             => 'Transferencia ' . $from->displayLabel() . ' → ' . $to->displayLabel(),
        ]);

        $email->update(['status' => BankEmail::STATUS_REGISTERED, 'transaction_id' => $tx->id]);
        AuditLog::record('bank_email_own_transfer', ['transaction_id' => $tx->id, 'bank_email_id' => $email->id]);

        return $tx;
    }

    /** Aplica el recurrente con el monto y fecha del correo */
    public function applyRecurring(BankEmail $email, RecurringCharge $charge): ?Transaction
    {
        $p = $email->parsed;

        if ($email->status !== BankEmail::STATUS_PENDING || ! $charge->is_active || $charge->account_id !== $email->account_id) {
            return null;
        }

        $tx = $this->charges->applyCharge($charge, $p['amount'], $p['date']);

        $email->update(['status' => BankEmail::STATUS_REGISTERED, 'transaction_id' => $tx->id]);
        AuditLog::record('bank_email_recurring_apply', ['transaction_id' => $tx->id, 'bank_email_id' => $email->id, 'recurring_charge_id' => $charge->id]);

        return $tx;
    }

    /** Corrige al monto real un recurrente que ya se había aplicado con el estimado */
    public function adjustTransaction(BankEmail $email, Transaction $tx): ?Transaction
    {
        $p = $email->parsed;

        if ($email->status !== BankEmail::STATUS_PENDING || $tx->account_id !== $email->account_id) {
            return null;
        }

        $tx->update(['amount' => $p['amount'], 'date' => $p['date']]);

        $email->update(['status' => BankEmail::STATUS_REGISTERED, 'transaction_id' => $tx->id]);
        AuditLog::record('bank_email_recurring_adjust', ['transaction_id' => $tx->id, 'bank_email_id' => $email->id]);

        return $tx->fresh();
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
            // bank_last4 admite varias terminaciones: "379, 894" (cuenta y tarjeta de débito)
            $byDigits = Account::where('is_active', true)
                ->whereNotNull('bank_last4')
                ->get()
                ->first(function (Account $a) use ($last4) {
                    foreach (preg_split('/[\s,;]+/', (string) $a->bank_last4, -1, PREG_SPLIT_NO_EMPTY) as $digits) {
                        if (str_ends_with($digits, $last4) || str_ends_with($last4, $digits)) {
                            return true;
                        }
                    }

                    return false;
                });

            if ($byDigits) {
                return $byDigits;
            }
        }

        if ($bank) {
            $candidates = Account::where('is_active', true)->where('institution', $bank)->get();

            if ($candidates->count() === 1) {
                return $candidates->first();
            }

            // Varias cuentas en la institución (ej. Nu débito + cajitas): la de débito es la operativa
            $debit = $candidates->where('type', Account::TYPE_DEBIT);

            if ($debit->count() === 1) {
                return $debit->first();
            }
        }

        return null;
    }

    /** ¿El nombre de la contraparte es el del dueño? ("HANS,HATCH/DORANTES", "Hans Revolut") */
    private function isOwnName(string $who): bool
    {
        $words = $this->nameWords($who);
        $owner = $this->nameWords((string) User::query()->value('name'));

        if ($owner === []) {
            return false;
        }

        $hits = count(array_intersect($words, $owner));

        // Nombre + institución propia, como "Hans Revolut en STP"
        return $hits >= 2 || ($hits >= 1 && $this->hintedAccounts($who, Account::where('is_active', true)->get()) !== []);
    }

    /** Cuentas cuya institución aparece en el texto (para sugerirlas primero) */
    private function hintedAccounts(string $who, $accounts): array
    {
        $words = $this->nameWords($who);

        return $accounts
            ->filter(fn (Account $a) => array_intersect($this->nameWords($a->institutionLabel()), $words) !== [])
            ->pluck('id')->all();
    }

    private function nameWords(string $text): array
    {
        preg_match_all('/[a-z]{3,}/', Str::ascii(mb_strtolower($text)), $m);

        return array_values(array_diff(array_unique($m[0]), ['otra', 'cuenta', 'banco', 'mexico']));
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
