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

    // ── Retomar correos sin responder ─────────────────────────────────

    /** Correos que siguen esperando respuesta (los de moneda extranjera se capturan en la web) */
    public function pendingEmails()
    {
        return BankEmail::where('status', BankEmail::STATUS_PENDING)->orderBy('received_at')->orderBy('id')->get()
            ->filter(fn (BankEmail $e) => is_array($e->parsed) && ($e->parsed['currency'] ?? 'MXN') === 'MXN')
            ->values();
    }

    /**
     * «Volver a leer correos»: las preguntas de Telegram caducan, pero el correo
     * sigue pendiente. Lee el buzón por si hay nuevos y vuelve a enrutar los que
     * se quedaron sin respuesta; los que ya se registraron por otro lado
     * (importación, captura manual) se cierran solos.
     *
     * @return array{new:int, closed:int, registered:int, asked:int}
     */
    public function retry(?BankEmail $only = null, int $days = 7): array
    {
        $stale = $only ? collect([$only]) : $this->pendingEmails();
        $new   = 0;

        // Las preguntas a medias se descartan: se vuelven a hacer desde el correo
        $this->telegramFlow->resetPending();

        if ($only === null && $this->mailbox->isConfigured()) {
            try {
                $new = $this->sync($days);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $result = ['new' => $new, 'closed' => 0, 'registered' => 0, 'asked' => 0];

        foreach ($stale as $email) {
            $email->refresh();

            if ($email->status !== BankEmail::STATUS_PENDING || ! is_array($email->parsed)) {
                continue;
            }

            $this->route($email, true);

            $result[match ($email->refresh()->status) {
                BankEmail::STATUS_DUPLICATE  => 'closed',
                BankEmail::STATUS_REGISTERED => 'registered',
                default                      => 'asked',
            }]++;
        }

        return $result;
    }

    /** Aviso diario: cuántos correos siguen sin responder, con botón para retomarlos */
    public function remindPending(): int
    {
        $count = $this->pendingEmails()->count();

        if ($count > 0) {
            $this->notify(
                '📬 Tienes ' . $count . ($count === 1 ? ' correo del banco' : ' correos del banco') . ' sin responder.',
                [[['text' => '🔄 Volver a leer correos', 'callback_data' => 'mail:retry']]]
            );
        }

        return $count;
    }

    // ── Enrutamiento ──────────────────────────────────────────────────

    /** $retry: segunda vuelta de un correo cuya pregunta caducó */
    private function route(BankEmail $email, bool $retry = false): void
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

        // Movimiento interno entre la cuenta y su apartado/inversión del mismo banco
        if (in_array($p['kind'], ['to_savings', 'from_savings'], true) && $account) {
            $savings = Account::where('is_active', true)->where('institution', $account->institution)
                ->whereIn('type', [Account::TYPE_SAVINGS, Account::TYPE_INVESTMENT])->get();

            if ($savings->count() === 1) {
                $p['kind'] === 'to_savings'
                    ? $this->registerInternalMove($email, $account, $savings->first())
                    : $this->registerInternalMove($email, $savings->first(), $account);

                return;
            }
        }

        $type = $p['kind'] === 'income' ? 'income' : 'expense';

        // 2) Duplicado por número de autorización
        if ($p['auth'] && BankEmail::where('auth_number', $p['auth'])->where('id', '<>', $email->id)->where('status', '<>', BankEmail::STATUS_IGNORED)->exists()) {
            $email->update(['status' => BankEmail::STATUS_DUPLICATE]);

            return;
        }

        $direction = $type === 'income' ? 'in' : 'out';

        // 3) Ya registrado en esta cuenta (también si fue como transferencia desde la otra cuenta)
        // Al retomar, lo normal es que ya se haya registrado al importar el estado de cuenta
        // (con la fecha del banco, que puede diferir un par de días de la del correo)
        if ($account && ($dup = $this->matcher->existing($account, $direction, $p['amount'], $p['date'], $retry ? 3 : 1))) {
            // Retomado y el movimiento no pertenece a otro correo: se cierra sin preguntar
            if ($retry && ! BankEmail::where('transaction_id', $dup->id)->where('id', '<>', $email->id)->exists()) {
                $email->update(['status' => BankEmail::STATUS_DUPLICATE, 'transaction_id' => $dup->id]);

                return;
            }

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

        // 4a') Retiro de cajero: el dinero pasa a tu efectivo, no es gasto todavía
        if ($account && $type === 'expense' && $account->type !== Account::TYPE_CASH
            && Account::isCashWithdrawal($p['description']) && ($cash = Account::cashAccount())) {
            $this->registerInternalMove($email, $account, $cash);

            return;
        }

        // 4a) El correo trae banco + terminación de una cuenta tuya: transferencia sin preguntar
        if ($account && ($own = $this->ownAccountByClabe($p['counterparty_bank'] ?? null, $p['counterparty_last4'] ?? null, $account))) {
            $type === 'income'
                ? $this->registerInternalMove($email, $own, $account)
                : $this->registerInternalMove($email, $account, $own);

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

    /**
     * Cuenta → apartado del mismo banco. Si ya estaba registrada se cierra el
     * correo; si no, se registra y se avisa con «Deshacer».
     */
    private function registerInternalMove(BankEmail $email, Account $from, Account $to): void
    {
        $p   = $email->parsed;
        $dup = $this->matcher->existing($from, 'out', $p['amount'], $p['date'], 1);

        if ($dup && $dup->type === Transaction::TYPE_TRANSFER && $dup->counterparty_account_id === $to->id) {
            $email->update(['status' => BankEmail::STATUS_DUPLICATE, 'transaction_id' => $dup->id]);

            return;
        }

        $tx = Transaction::create([
            'date'                    => $p['date'],
            'type'                    => Transaction::TYPE_TRANSFER,
            'amount'                  => $p['amount'],
            'account_id'              => $from->id,
            'counterparty_account_id' => $to->id,
            'description'             => 'Transferencia ' . $from->displayLabel() . ' → ' . $to->displayLabel(),
        ]);

        $email->update(['status' => BankEmail::STATUS_REGISTERED, 'transaction_id' => $tx->id]);
        AuditLog::record('bank_email_internal_move', ['transaction_id' => $tx->id, 'bank_email_id' => $email->id]);

        $this->notify(
            '🔁 Registrado desde correo de ' . ucfirst($email->bank) . "\n"
                . format_currency($tx->amount) . ' · ' . $tx->description . ' · ' . $tx->date->translatedFormat('j M Y'),
            [[['text' => '↩️ Deshacer', 'callback_data' => 'mail:undo:' . $email->id]]]
        );
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

    /**
     * Cuenta propia a partir del banco y la terminación de CLABE del correo.
     * La CLABE termina en un dígito verificador: "3793" es la cuenta "…379".
     */
    private function ownAccountByClabe(?string $bank, ?string $last4, Account $exclude): ?Account
    {
        if (! $last4) {
            return null;
        }

        $bankWords = $bank ? $this->nameWords($bank) : [];
        $body      = substr($last4, 0, -1);

        $matches = Account::where('is_active', true)->where('id', '<>', $exclude->id)->whereNotNull('bank_last4')->get()
            ->filter(function (Account $a) use ($last4, $body, $bankWords) {
                if ($bankWords !== [] && array_intersect($this->nameWords($a->institutionLabel()), $bankWords) === []) {
                    return false;
                }

                foreach (preg_split('/[\s,;]+/', (string) $a->bank_last4, -1, PREG_SPLIT_NO_EMPTY) as $digits) {
                    if (str_ends_with($last4, $digits) || ($body !== '' && str_ends_with($body, $digits))) {
                        return true;
                    }
                }

                return false;
            });

        return $matches->count() === 1 ? $matches->first() : null;
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
