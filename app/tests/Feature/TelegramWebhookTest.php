<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET  = 'test-secret';
    private const CHAT_ID = 111222333;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token'      => 'test-token',
            'services.telegram.chat_id'        => self::CHAT_ID,
            'services.telegram.webhook_secret' => self::SECRET,
            'services.deepseek.api_key'        => null,
        ]);

        Http::preventStrayRequests();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
    }

    private function account(): Account
    {
        return Account::create([
            'name'            => 'Nu Débito',
            'type'            => 'debit',
            'institution'     => 'nu',
            'initial_balance' => '0.00',
            'color'           => '#76a72b',
        ]);
    }

    private function category(string $name = 'Comida'): Category
    {
        return Category::create(['name' => $name, 'kind' => 'expense']);
    }

    private function postUpdate(array $update, ?string $secret = self::SECRET)
    {
        $headers = $secret !== null ? ['X-Telegram-Bot-Api-Secret-Token' => $secret] : [];

        return $this->postJson('/telegram/webhook', $update, $headers);
    }

    private function textMessage(string $text, int $chatId = self::CHAT_ID): array
    {
        return ['message' => ['chat' => ['id' => $chatId], 'text' => $text]];
    }

    private function callbackUpdate(string $data): array
    {
        return ['callback_query' => [
            'id'      => 'cb1',
            'data'    => $data,
            'message' => ['message_id' => 10, 'chat' => ['id' => self::CHAT_ID]],
        ]];
    }

    // ── Seguridad ─────────────────────────────────────────────────────────

    public function test_rejects_request_without_secret_token(): void
    {
        $this->postUpdate($this->textMessage('250 tacos'), null)->assertForbidden();
        $this->postUpdate($this->textMessage('250 tacos'), 'wrong')->assertForbidden();
    }

    public function test_ignores_messages_from_other_chats(): void
    {
        $this->account();

        $this->postUpdate($this->textMessage('250 tacos', 999))->assertNoContent();

        Http::assertNothingSent();
        $this->assertSame(0, Transaction::count());
    }

    // ── Flujo de captura ──────────────────────────────────────────────────

    public function test_expense_with_guessed_category_is_created_after_account_pick(): void
    {
        $account = $this->account();
        $category = $this->category('Comida');

        // "250 comida tacos" → adivina categoría "Comida", pregunta cuenta
        $this->postUpdate($this->textMessage('250 comida tacos'))->assertNoContent();

        // Elige cuenta → crea el movimiento
        $this->postUpdate($this->callbackUpdate('acc:' . $account->id))->assertNoContent();

        $tx = Transaction::sole();
        $this->assertSame('expense', $tx->type);
        $this->assertSame('250.00', $tx->amount);
        $this->assertSame($account->id, $tx->account_id);
        $this->assertSame($category->id, $tx->category_id);
        $this->assertSame('Comida tacos', $tx->description);
        $this->assertSame(now()->toDateString(), $tx->date->toDateString());
    }

    public function test_expense_without_category_match_asks_for_category(): void
    {
        $account  = $this->account();
        $category = $this->category('Transporte');

        $this->postUpdate($this->textMessage('1,234.56 uber aeropuerto'))->assertNoContent();
        $this->postUpdate($this->callbackUpdate('acc:' . $account->id))->assertNoContent();

        // Aún no existe: falta elegir categoría
        $this->assertSame(0, Transaction::count());

        $this->postUpdate($this->callbackUpdate('cat:' . $category->id))->assertNoContent();

        $tx = Transaction::sole();
        $this->assertSame('1234.56', $tx->amount);
        $this->assertSame($category->id, $tx->category_id);
        $this->assertSame('Uber aeropuerto', $tx->description);
    }

    public function test_expense_with_relative_date_is_registered_yesterday(): void
    {
        $account = $this->account();
        $this->category('Comida');

        $this->postUpdate($this->textMessage('250 comida tacos ayer'))->assertNoContent();
        $this->postUpdate($this->callbackUpdate('acc:' . $account->id))->assertNoContent();

        $tx = Transaction::sole();
        $this->assertSame(now()->subDay()->toDateString(), $tx->date->toDateString());
        $this->assertSame('Comida tacos', $tx->description);
    }

    public function test_expense_with_explicit_date_uses_it(): void
    {
        $account = $this->account();
        $this->category('Comida');

        $this->postUpdate($this->textMessage('300 comida 15/07'))->assertNoContent();
        $this->postUpdate($this->callbackUpdate('acc:' . $account->id))->assertNoContent();

        $tx = Transaction::sole();
        $this->assertSame(now()->year . '-07-15', $tx->date->toDateString());
    }

    public function test_future_or_invalid_date_falls_back_to_today(): void
    {
        $account = $this->account();
        $this->category('Comida');

        // 31/02 no existe → queda como parte de la descripción y fecha de hoy
        $this->postUpdate($this->textMessage('100 comida 31/02'))->assertNoContent();
        $this->postUpdate($this->callbackUpdate('acc:' . $account->id))->assertNoContent();

        $tx = Transaction::sole();
        $this->assertSame(now()->toDateString(), $tx->date->toDateString());
    }

    public function test_non_amount_message_gets_help_and_creates_nothing(): void
    {
        $this->postUpdate($this->textMessage('hola'))->assertNoContent();
        $this->postUpdate($this->textMessage('/start'))->assertNoContent();

        $this->assertSame(0, Transaction::count());
    }

    public function test_natural_language_message_is_parsed_with_deepseek(): void
    {
        config(['services.deepseek.api_key' => 'test-ds-key']);

        $account  = $this->account();
        $category = $this->category('Comida');

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []]),
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'amount'      => '250.00',
                    'description' => 'Tacos',
                    'date'        => now()->subDay()->toDateString(),
                    'category'    => 'Comida',
                ])]]],
            ]),
        ]);

        $this->postUpdate($this->textMessage('gasté doscientos cincuenta en tacos ayer'))->assertNoContent();
        $this->postUpdate($this->callbackUpdate('acc:' . $account->id))->assertNoContent();

        $tx = Transaction::sole();
        $this->assertSame('250.00', $tx->amount);
        $this->assertSame('Tacos', $tx->description);
        $this->assertSame($category->id, $tx->category_id);
        $this->assertSame(now()->subDay()->toDateString(), $tx->date->toDateString());
    }

    public function test_llm_future_date_is_clamped_to_today(): void
    {
        config(['services.deepseek.api_key' => 'test-ds-key']);

        $account = $this->account();
        $this->category('Comida');

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []]),
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'amount'      => '100.00',
                    'description' => 'Cena',
                    'date'        => now()->addDays(5)->toDateString(),
                    'category'    => 'Comida',
                ])]]],
            ]),
        ]);

        $this->postUpdate($this->textMessage('cien de la cena'))->assertNoContent();
        $this->postUpdate($this->callbackUpdate('acc:' . $account->id))->assertNoContent();

        $this->assertSame(now()->toDateString(), Transaction::sole()->date->toDateString());
    }

    public function test_without_deepseek_key_natural_language_gets_help(): void
    {
        // setUp no define api_key de deepseek → fallback inactivo
        $this->postUpdate($this->textMessage('gasté un dineral en tacos'))->assertNoContent();

        $this->assertSame(0, Transaction::count());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'deepseek'));
    }

    public function test_callback_after_expiry_does_not_create_transaction(): void
    {
        $account = $this->account();

        // Callback sin pending previo (expiró o nunca existió)
        $this->postUpdate($this->callbackUpdate('acc:' . $account->id))->assertNoContent();

        $this->assertSame(0, Transaction::count());
    }
}
