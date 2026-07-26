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
        Http::fake([
            'api.telegram.org/*' => function ($request) {
                if (str_contains($request->url(), '/getFile')) {
                    return Http::response(['ok' => true, 'result' => ['file_path' => 'photos/test.jpg']]);
                }
                if (str_contains($request->url(), '/file/bot')) {
                    return Http::response('fake-image-bytes');
                }

                return Http::response(['ok' => true, 'result' => []]);
            },
        ]);
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

    private function photoMessage(?string $caption = null): array
    {
        $message = ['chat' => ['id' => self::CHAT_ID], 'photo' => [
            ['file_id' => 'small', 'width' => 90],
            ['file_id' => 'big', 'width' => 800],
        ]];

        if ($caption !== null) {
            $message['caption'] = $caption;
        }

        return ['message' => $message];
    }

    private function fakeVision(array $charges): void
    {
        config(['services.openai.api_key' => 'test-oa-key']);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['charges' => $charges])]]],
            ]),
        ]);
    }

    public function test_photo_with_multiple_charges_registers_each_one(): void
    {
        $account  = $this->account();
        $comida   = $this->category('Comida');
        $ingreso  = Category::create(['name' => 'Otros ingresos', 'kind' => 'income']);

        $this->fakeVision([
            ['amount' => '129.00', 'description' => 'Spotify', 'date' => now()->subDay()->toDateString(), 'type' => 'expense', 'category' => null],
            ['amount' => '500.00', 'description' => 'Devolución', 'date' => null, 'type' => 'income', 'category' => 'Otros ingresos'],
        ]);

        // Foto → resumen + pregunta el tipo del primer movimiento
        $this->postUpdate($this->photoMessage())->assertNoContent();

        // Mov 1: Hans dice cargo → cuenta → sin categoría adivinada → categoría → creado
        $this->postUpdate($this->callbackUpdate('typ:expense'))->assertNoContent();
        $this->postUpdate($this->callbackUpdate('acc:' . $account->id))->assertNoContent();
        $this->postUpdate($this->callbackUpdate('cat:' . $comida->id))->assertNoContent();

        $this->assertSame(1, Transaction::count());

        // Mov 2: Hans dice abono → cuenta → categoría (la pista sale como ⭐ sugerida)
        $this->postUpdate($this->callbackUpdate('typ:income'))->assertNoContent();
        $this->postUpdate($this->callbackUpdate('acc:' . $account->id))->assertNoContent();
        $this->postUpdate($this->callbackUpdate('cat:' . $ingreso->id))->assertNoContent();

        $this->assertSame(2, Transaction::count());

        $first = Transaction::where('description', 'Spotify')->sole();
        $this->assertSame('expense', $first->type);
        $this->assertSame('129.00', $first->amount);
        $this->assertSame($comida->id, $first->category_id);
        $this->assertSame(now()->subDay()->toDateString(), $first->date->toDateString());

        $second = Transaction::where('description', 'Devolución')->sole();
        $this->assertSame('income', $second->type);
        $this->assertSame('500.00', $second->amount);
        $this->assertSame($ingreso->id, $second->category_id);
        $this->assertSame(now()->toDateString(), $second->date->toDateString());
    }

    public function test_category_keyboard_navigates_group_to_child(): void
    {
        $account = $this->account();
        $root    = $this->category('Alimentación');
        $child   = Category::create(['name' => 'Restaurantes', 'kind' => 'expense', 'parent_id' => $root->id]);

        $this->fakeVision([
            ['amount' => '350.00', 'description' => 'Cena', 'date' => null, 'type' => 'expense', 'category' => null],
        ]);

        $this->postUpdate($this->photoMessage())->assertNoContent();
        $this->postUpdate($this->callbackUpdate('typ:expense'))->assertNoContent();
        $this->postUpdate($this->callbackUpdate('acc:' . $account->id))->assertNoContent();

        // Abrir grupo, regresar, abrir de nuevo y elegir subcategoría
        $this->postUpdate($this->callbackUpdate('catg:' . $root->id))->assertNoContent();
        $this->postUpdate($this->callbackUpdate('catb:1'))->assertNoContent();
        $this->postUpdate($this->callbackUpdate('catg:' . $root->id))->assertNoContent();
        $this->assertSame(0, Transaction::count());

        $this->postUpdate($this->callbackUpdate('cat:' . $child->id))->assertNoContent();

        $tx = Transaction::sole();
        $this->assertSame($child->id, $tx->category_id);
    }

    public function test_photo_movement_marked_as_interest_skips_category(): void
    {
        $account = $this->account();
        $this->category('Comida');

        $this->fakeVision([
            ['amount' => '45.30', 'description' => 'Rendimientos', 'date' => null, 'type' => 'income', 'category' => null],
        ]);

        $this->postUpdate($this->photoMessage())->assertNoContent();
        $this->postUpdate($this->callbackUpdate('typ:interest'))->assertNoContent();
        $this->postUpdate($this->callbackUpdate('acc:' . $account->id))->assertNoContent();

        $tx = Transaction::sole();
        $this->assertSame('interest', $tx->type);
        $this->assertSame('45.30', $tx->amount);
        $this->assertNull($tx->category_id);
    }

    public function test_photo_duplicate_can_be_skipped(): void
    {
        $account = $this->account();
        $this->category('Comida');

        // Ya existe un movimiento igual (mismo monto, fecha cercana)
        Transaction::create([
            'date'       => now()->subDay()->toDateString(),
            'type'       => 'expense',
            'amount'     => '129.00',
            'account_id' => $account->id,
            'description' => 'Spotify',
        ]);

        $this->fakeVision([
            ['amount' => '129.00', 'description' => 'Spotify', 'date' => now()->toDateString(), 'type' => 'expense', 'category' => null],
        ]);

        // Foto → advertencia de duplicado → omitir
        $this->postUpdate($this->photoMessage())->assertNoContent();
        $this->postUpdate($this->callbackUpdate('dup:skip'))->assertNoContent();

        $this->assertSame(1, Transaction::count()); // solo el preexistente

        // Y ya no hay pendiente activo: un callback posterior no crea nada
        $this->postUpdate($this->callbackUpdate('typ:expense'))->assertNoContent();
        $this->assertSame(1, Transaction::count());
    }

    public function test_photo_duplicate_can_be_registered_anyway(): void
    {
        $account = $this->account();
        $comida  = $this->category('Comida');

        Transaction::create([
            'date'       => now()->toDateString(),
            'type'       => 'expense',
            'amount'     => '129.00',
            'account_id' => $account->id,
            'description' => 'Spotify',
        ]);

        $this->fakeVision([
            ['amount' => '129.00', 'description' => 'Spotify', 'date' => now()->toDateString(), 'type' => 'expense', 'category' => 'Comida'],
        ]);

        $this->postUpdate($this->photoMessage())->assertNoContent();
        $this->postUpdate($this->callbackUpdate('dup:keep'))->assertNoContent();
        $this->postUpdate($this->callbackUpdate('typ:expense'))->assertNoContent();
        $this->postUpdate($this->callbackUpdate('acc:' . $account->id))->assertNoContent();
        $this->postUpdate($this->callbackUpdate('cat:' . $comida->id))->assertNoContent();

        $this->assertSame(2, Transaction::count());
        $this->assertSame($comida->id, Transaction::orderByDesc('id')->first()->category_id);
    }

    public function test_movement_can_be_discarded_at_any_step(): void
    {
        $account = $this->account();
        $this->category('Comida');

        $this->fakeVision([
            ['amount' => '100.00', 'description' => 'Uno', 'date' => null, 'type' => 'expense', 'category' => null],
            ['amount' => '200.00', 'description' => 'Dos', 'date' => null, 'type' => 'expense', 'category' => null],
        ]);

        $this->postUpdate($this->photoMessage())->assertNoContent();

        // Mov 1: descartado desde la pregunta de tipo
        $this->postUpdate($this->callbackUpdate('skp:1'))->assertNoContent();
        $this->assertSame(0, Transaction::count());

        // Mov 2: avanza a cuenta y se descarta ahí
        $this->postUpdate($this->callbackUpdate('typ:expense'))->assertNoContent();
        $this->postUpdate($this->callbackUpdate('skp:1'))->assertNoContent();

        $this->assertSame(0, Transaction::count());

        // Ya no hay pendientes: nada que crear
        $this->postUpdate($this->callbackUpdate('acc:' . $account->id))->assertNoContent();
        $this->assertSame(0, Transaction::count());
    }

    public function test_photo_without_vision_key_informs_user(): void
    {
        $this->postUpdate($this->photoMessage())->assertNoContent();

        $this->assertSame(0, Transaction::count());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'openai'));
    }

    public function test_photo_with_no_readable_charges_creates_nothing(): void
    {
        $this->account();
        $this->fakeVision([]);

        $this->postUpdate($this->photoMessage())->assertNoContent();

        $this->assertSame(0, Transaction::count());
    }

    public function test_recurring_notification_apply_button_creates_transaction(): void
    {
        $account = $this->account();

        $charge = \App\Models\RecurringCharge::create([
            'name'                  => 'Netflix',
            'account_id'            => $account->id,
            'type'                  => 'expense',
            'amount'                => '199.00',
            'day_of_month'          => now()->day,
            'start_date'            => now()->subMonths(2)->toDateString(),
            'next_application_date' => now()->toDateString(),
            'is_active'             => true,
        ]);

        $this->postUpdate($this->callbackUpdate('rec:apply:' . $charge->id))->assertNoContent();

        $tx = Transaction::sole();
        $this->assertSame('expense', $tx->type);
        $this->assertSame('199.00', $tx->amount);
        $this->assertSame(now()->toDateString(), $tx->date->toDateString());

        // La fecha del cargo avanzó: segundo clic no duplica
        $this->postUpdate($this->callbackUpdate('rec:apply:' . $charge->id))->assertNoContent();
        $this->assertSame(1, Transaction::count());
    }

    public function test_recurring_notification_skip_button_changes_nothing(): void
    {
        $account = $this->account();

        $charge = \App\Models\RecurringCharge::create([
            'name'                  => 'Spotify',
            'account_id'            => $account->id,
            'type'                  => 'expense',
            'amount'                => '129.00',
            'day_of_month'          => now()->day,
            'start_date'            => now()->subMonth()->toDateString(),
            'next_application_date' => now()->toDateString(),
            'is_active'             => true,
        ]);

        $this->postUpdate($this->callbackUpdate('rec:skip:' . $charge->id))->assertNoContent();

        $this->assertSame(0, Transaction::count());
        $this->assertSame(now()->toDateString(), $charge->fresh()->next_application_date->toDateString());
    }

    public function test_callback_after_expiry_does_not_create_transaction(): void
    {
        $account = $this->account();

        // Callback sin pending previo (expiró o nunca existió)
        $this->postUpdate($this->callbackUpdate('acc:' . $account->id))->assertNoContent();

        $this->assertSame(0, Transaction::count());
    }
}
