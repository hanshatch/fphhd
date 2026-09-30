<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankEmail;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\Mail\BankEmailImportService;
use App\Services\Mail\MailboxReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BankEmailImportTest extends TestCase
{
    use RefreshDatabase;

    private const CHAT_ID = 12345;
    private const SECRET  = 'test-secret';

    /** @var array<int, array> */
    public array $inbox = [];

    /** @var array<int, array> mensajes enviados a Telegram (payloads) */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token'      => 'test-token',
            'services.telegram.chat_id'        => self::CHAT_ID,
            'services.telegram.webhook_secret' => self::SECRET,
            'services.deepseek.api_key'        => null,
            'services.openai.api_key'          => null,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'api.telegram.org/*' => function ($request) {
                $this->sent[] = $request->data();

                return Http::response(['ok' => true, 'result' => ['message_id' => count($this->sent)]]);
            },
        ]);

        $this->app->bind(MailboxReader::class, fn () => new class($this) implements MailboxReader {
            public function __construct(private BankEmailImportTest $t) {}
            public function isConfigured(): bool { return true; }
            public function fetchRecent(int $days = 7): array { return $this->t->inbox; }
        });
    }

    private function account(string $name, string $institution, ?string $last4 = null): Account
    {
        return Account::create([
            'name' => $name, 'type' => 'debit', 'institution' => $institution, 'bank_last4' => $last4,
            'initial_balance' => '0.00', 'color' => '#000000',
        ]);
    }

    private function mail(string $uid, string $from, string $subject, string $text, string $date): array
    {
        return ['uid' => $uid, 'from' => $from, 'subject' => $subject, 'text' => $text, 'date' => Carbon::parse($date)];
    }

    private function tgCallback(string $data)
    {
        return $this->postJson('/telegram/webhook', ['callback_query' => [
            'id' => 'cb1', 'data' => $data,
            'message' => ['chat' => ['id' => self::CHAT_ID], 'message_id' => 1],
        ]], ['X-Telegram-Bot-Api-Secret-Token' => self::SECRET]);
    }

    private function lastText(): string
    {
        $sends = array_values(array_filter($this->sent, fn ($p) => isset($p['text'])));

        return (string) (end($sends)['text'] ?? '');
    }

    // ── Casos ─────────────────────────────────────────────────────────

    public function test_banamex_charge_asks_category_with_account_resolved_and_registers(): void
    {
        $cheques = $this->account('Cheques', 'banamex', '379');
        $otros   = Category::create(['name' => 'Otros gastos', 'kind' => 'expense']);

        $this->inbox = [$this->mail('u1', 'notificaciones@banamex.com', 'Retiro/Compra con cuenta Banamex', BankEmailParserTest::BANAMEX_RETIRO, '2026-09-30 14:56')];

        $this->assertSame(1, app(BankEmailImportService::class)->sync());

        $email = BankEmail::sole();
        $this->assertSame(BankEmail::STATUS_PENDING, $email->status);
        $this->assertSame($cheques->id, $email->account_id);
        $this->assertSame('383450', $email->auth_number);

        $this->assertStringContainsString('📧 Banamex', $this->lastText());
        $this->assertStringContainsString('¿Qué categoría?', $this->lastText());
        $this->assertStringContainsString('Cheques', $this->lastText());

        // Hans elige categoría → se registra en la cuenta correcta y el correo queda ligado
        $this->tgCallback('cat:' . $otros->id)->assertNoContent();

        $tx = Transaction::sole();
        $this->assertSame('20000.00', $tx->amount);
        $this->assertSame($cheques->id, $tx->account_id);
        $this->assertSame($otros->id, $tx->category_id);
        $this->assertSame('2026-09-30', $tx->date->toDateString());
        $this->assertSame(BankEmail::STATUS_REGISTERED, $email->fresh()->status);
        $this->assertSame($tx->id, $email->fresh()->transaction_id);

        // Segunda pasada: el mismo correo no se vuelve a procesar
        $this->assertSame(0, app(BankEmailImportService::class)->sync());
    }

    public function test_charge_matching_a_deposit_elsewhere_is_offered_as_transfer(): void
    {
        $cheques = $this->account('Cheques', 'banamex', '379');
        $revolut = $this->account('Revolut', 'revolut');

        $deposit = Transaction::create([
            'date' => '2026-09-30', 'type' => 'income', 'amount' => '20000.00',
            'account_id' => $revolut->id, 'description' => 'Transferencia interbancaria',
        ]);

        $this->inbox = [$this->mail('u2', 'notificaciones@banamex.com', 'Retiro/Compra con cuenta Banamex', BankEmailParserTest::BANAMEX_RETIRO, '2026-09-30 14:56')];
        app(BankEmailImportService::class)->sync();

        $this->assertStringContainsString('Parece transferencia interna: Cheques → Revolut', $this->lastText());

        $email = BankEmail::sole();
        $this->tgCallback("mail:xfer:{$email->id}:{$deposit->id}")->assertNoContent();

        $tx = Transaction::sole();
        $this->assertSame('transfer', $tx->type);
        $this->assertSame($cheques->id, $tx->account_id);
        $this->assertSame($revolut->id, $tx->counterparty_account_id);
        $this->assertNull($tx->category_id);
        $this->assertSame(BankEmail::STATUS_REGISTERED, $email->fresh()->status);
    }

    public function test_transfer_registered_from_other_account_is_reported_as_duplicate(): void
    {
        $cheques = $this->account('Cheques', 'banamex', '379');
        $revolut = $this->account('Revolut', 'revolut');

        Transaction::create([
            'date' => '2026-09-30', 'type' => 'transfer', 'amount' => '20000.00',
            'account_id' => $cheques->id, 'counterparty_account_id' => $revolut->id,
            'description' => 'Transferencia a Revolut',
        ]);

        $this->inbox = [$this->mail('u11', 'notificaciones@banamex.com', 'Retiro/Compra con cuenta Banamex', BankEmailParserTest::BANAMEX_RETIRO, '2026-09-30 14:56')];
        app(BankEmailImportService::class)->sync();

        $this->assertStringContainsString('Ya tienes un movimiento parecido registrado', $this->lastText());
        $this->assertStringContainsString('Transferencia Banamex · Cheques → Revolut', $this->lastText());
        $this->assertSame(1, Transaction::count());
    }

    public function test_direct_debit_email_proposes_recurring_charge_and_applies_real_amount(): void
    {
        $cheques = $this->account('Cheques', 'banamex', '379, 894');
        $cat     = Category::create(['name' => 'Seguros', 'kind' => 'expense']);
        $charge  = \App\Models\RecurringCharge::create([
            'name' => 'Seguros Monterrey Retiro - 3', 'account_id' => $cheques->id, 'category_id' => $cat->id,
            'type' => 'expense', 'amount' => '11019.03', 'day_of_month' => 29,
            'start_date' => '2026-01-01', 'next_application_date' => '2026-09-29', 'is_active' => true,
        ]);

        $this->inbox = [$this->mail('u12', 'notificaciones@banamex.com', 'Cargo a cuenta para pago a Establecimiento', BankEmailParserTest::BANAMEX_DOMICILIADO, '2026-09-29 19:27')];
        app(BankEmailImportService::class)->sync();

        $this->assertStringContainsString('Parece el cargo recurrente «Seguros Monterrey Retiro - 3»', $this->lastText());
        $this->assertSame(0, Transaction::count());

        $email = BankEmail::sole();
        $this->tgCallback("mail:rec:{$email->id}:{$charge->id}")->assertNoContent();

        $tx = Transaction::sole();
        $this->assertSame('11040.20', $tx->amount);
        $this->assertSame($cat->id, $tx->category_id);
        $this->assertSame('2026-09-29', $tx->date->toDateString());
        $this->assertSame('2026-10-29', $charge->fresh()->next_application_date->toDateString());
        $this->assertSame(BankEmail::STATUS_REGISTERED, $email->fresh()->status);
    }

    public function test_existing_same_movement_is_flagged_as_duplicate(): void
    {
        $cheques = $this->account('Cheques', 'banamex', '379');

        Transaction::create([
            'date' => '2026-09-29', 'type' => 'income', 'amount' => '50411.94',
            'account_id' => $cheques->id, 'description' => 'Pago cliente',
        ]);

        $this->inbox = [$this->mail('u3', 'notificaciones@banamex.com', 'Depósito a cuenta Banamex', BankEmailParserTest::BANAMEX_DEPOSITO, '2026-09-29 02:03')];
        app(BankEmailImportService::class)->sync();

        $this->assertStringContainsString('Ya tienes un movimiento parecido registrado', $this->lastText());
        $this->assertStringContainsString('Pago cliente', $this->lastText());

        $this->tgCallback('dup:skip')->assertNoContent();

        $this->assertSame(1, Transaction::count());
        $this->assertSame(BankEmail::STATUS_SKIPPED, BankEmail::sole()->status);
    }

    public function test_foreign_currency_sends_link_to_register_on_web(): void
    {
        $revolut = $this->account('Revolut', 'revolut');

        $this->inbox = [$this->mail('u4', 'no-reply@revolut.com', 'Enviaste 5.800 BRL a Samanta Motta Hatch 💸', BankEmailParserTest::REVOLUT_ENVIO, '2026-09-30 15:09')];
        app(BankEmailImportService::class)->sync();

        $this->assertStringContainsString('5,800.00 BRL', $this->lastText());
        $this->assertStringContainsString('No sé el monto en pesos', $this->lastText());

        $last     = end($this->sent);
        $keyboard = json_decode($last['reply_markup'] ?? '{}', true);
        $url      = $keyboard['inline_keyboard'][0][0]['url'] ?? '';

        $this->assertStringContainsString('/accounts/' . $revolut->id . '?new=1', $url);
        $this->assertStringContainsString('description=Transferencia', $url);
        $this->assertSame(0, Transaction::count());
        $this->assertSame(BankEmail::STATUS_PENDING, BankEmail::sole()->status);
    }

    public function test_known_merchant_is_registered_automatically_with_undo(): void
    {
        $revolut = $this->account('Revolut', 'revolut');
        $super   = Category::create(['name' => 'Súper', 'kind' => 'expense']);

        // Hans ya categorizó OXXO antes → la memoria lo sabe
        Transaction::create([
            'date' => '2026-09-03', 'type' => 'expense', 'amount' => '64.50',
            'account_id' => $revolut->id, 'category_id' => $super->id, 'description' => 'OXXO',
        ]);

        $this->inbox = [$this->mail('u5', 'no-reply@revolut.com', 'Pagaste 64,50 MXN en OXXO', "Hola Hans,\nEl martes, 8 septiembre, 10:08", '2026-09-08 10:09')];
        app(BankEmailImportService::class)->sync();

        $this->assertSame(2, Transaction::count());
        $new = Transaction::orderByDesc('id')->first();
        $this->assertSame('64.50', $new->amount);
        $this->assertSame($super->id, $new->category_id);
        $this->assertSame('2026-09-08', $new->date->toDateString());
        $this->assertStringContainsString('✅ Registrado desde correo de Revolut', $this->lastText());

        $email = BankEmail::sole();
        $this->assertSame(BankEmail::STATUS_REGISTERED, $email->status);

        $this->tgCallback("mail:undo:{$email->id}")->assertNoContent();

        $this->assertSame(1, Transaction::count());
        $this->assertSame(BankEmail::STATUS_SKIPPED, $email->fresh()->status);
    }

    public function test_unknown_sender_is_ignored_silently_and_unparsed_bank_mail_is_reported(): void
    {
        $this->inbox = [
            $this->mail('u6', 'promos@tienda.com', 'Ofertas de otoño', 'Compra ya', '2026-09-30 10:00'),
            $this->mail('u7', 'notificaciones@banamex.com', 'Tu estado de cuenta ya está disponible', 'Hola', '2026-09-30 10:01'),
        ];
        app(BankEmailImportService::class)->sync();

        $this->assertSame(BankEmail::STATUS_IGNORED, BankEmail::where('message_uid', 'u6')->sole()->status);
        $this->assertSame(BankEmail::STATUS_UNPARSED, BankEmail::where('message_uid', 'u7')->sole()->status);
        $this->assertCount(1, $this->sent);
        $this->assertStringContainsString('no supe leer', $this->lastText());
    }

    public function test_silent_sync_stores_old_mail_without_notifying(): void
    {
        $this->account('Cheques', 'banamex', '379');

        $this->inbox = [$this->mail('u8', 'notificaciones@banamex.com', 'Retiro/Compra con cuenta Banamex', BankEmailParserTest::BANAMEX_RETIRO, '2026-09-30 14:56')];

        $this->assertSame(1, app(BankEmailImportService::class)->sync(7, true));
        $this->assertSame(BankEmail::STATUS_SKIPPED, BankEmail::sole()->status);
        $this->assertSame('383450', BankEmail::sole()->auth_number);
        $this->assertCount(0, $this->sent);

        // La corrida normal posterior ya no lo vuelve a tocar
        $this->assertSame(0, app(BankEmailImportService::class)->sync());
        $this->assertCount(0, $this->sent);
    }

    public function test_info_mail_is_ignored_silently_and_multiple_last4_match(): void
    {
        $cheques = $this->account('Cheques', 'banamex', '379, 894');
        Category::create(['name' => 'Seguros', 'kind' => 'expense']);

        $this->inbox = [
            $this->mail('u9', 'notificaciones@banamex.com', 'Autorización de cargo a cuenta para pago a establecimiento', "Se registró una nueva domiciliación\nCuenta de cargo: 894", '2026-09-29 19:16'),
            $this->mail('u10', 'notificaciones@banamex.com', 'Cargo a cuenta para pago a Establecimiento', BankEmailParserTest::BANAMEX_DOMICILIADO, '2026-09-29 19:27'),
        ];
        app(BankEmailImportService::class)->sync();

        $this->assertSame(BankEmail::STATUS_IGNORED, BankEmail::where('message_uid', 'u9')->sole()->status);

        $charge = BankEmail::where('message_uid', 'u10')->sole();
        $this->assertSame(BankEmail::STATUS_PENDING, $charge->status);
        $this->assertSame($cheques->id, $charge->account_id);
        $this->assertCount(1, $this->sent);
        $this->assertStringContainsString('Seguros Monterrey Ne', $this->lastText());
        $this->assertStringContainsString('¿Qué categoría?', $this->lastText());
    }

    public function test_account_form_saves_bank_last4(): void
    {
        $account = $this->account('Cheques', 'banamex');

        $this->actingAs(\App\Models\User::factory()->create())
            ->withSession(['totp_verified' => true]);

        $this->assertNull($account->bank_last4);
        $account->update(['bank_last4' => '379']);
        $this->assertSame('379', $account->fresh()->bank_last4);
    }
}
