<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\WithTotpSession;

class StatementImportTest extends TestCase
{
    use RefreshDatabase, WithTotpSession;

    private function account(): Account
    {
        return Account::create([
            'name'            => 'Amex',
            'type'            => 'credit',
            'institution'     => 'other',
            'initial_balance' => '0.00',
            'color'           => '#373737',
        ]);
    }

    private function fakeVision(array $charges): void
    {
        config(['services.openai.api_key' => 'test-oa-key']);

        Http::preventStrayRequests();
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['charges' => $charges])]]],
            ]),
        ]);
    }

    private function upload(Account $account, int $files = 1)
    {
        $images = [];
        for ($i = 0; $i < $files; $i++) {
            $images[] = UploadedFile::fake()->image("edo{$i}.png", 800, 1200);
        }

        return $this->actingAsVerified(User::factory()->create())
            ->post(route('accounts.import.upload', $account), ['images' => $images]);
    }

    public function test_upload_analyzes_and_shows_review_with_category_hint(): void
    {
        $account = $this->account();
        $super   = Category::create(['name' => 'Súper', 'kind' => 'expense']);

        $this->fakeVision([
            ['amount' => '4530.68', 'description' => 'Vallarta Satélite', 'date' => '2026-08-28', 'type' => 'expense', 'category' => 'Súper'],
            ['amount' => '179.00',  'description' => 'Apple.com/bill',    'date' => '2026-08-24', 'type' => 'expense', 'category' => null],
        ]);

        $response = $this->upload($account);
        $response->assertRedirect();

        $review = $this->get($response->headers->get('Location'));
        $review->assertOk()
            ->assertSee('Vallarta Satélite')
            ->assertSee('Apple.com/bill')
            ->assertSee("value: '" . $super->id . "'", false)
            ->assertSee('Buscar categoría');

        $this->assertSame(0, Transaction::count());
    }

    public function test_learned_merchant_category_beats_model_hint(): void
    {
        $account = $this->account();
        $super   = Category::create(['name' => 'Súper', 'kind' => 'expense']);
        $otros   = Category::create(['name' => 'Otros gastos', 'kind' => 'expense']);

        // Hans ya registró OXXO como Súper antes
        Transaction::create([
            'date' => '2026-08-01', 'type' => 'expense', 'amount' => '50.00',
            'account_id' => $account->id, 'category_id' => $super->id, 'description' => 'OXXO',
        ]);

        $this->fakeVision([
            ['amount' => '64.50', 'description' => 'Oxxo', 'date' => '2026-09-08', 'type' => 'expense', 'category' => 'Otros gastos'],
        ]);

        $location = $this->upload($account)->headers->get('Location');

        $this->get($location)
            ->assertOk()
            ->assertSee('Aprendida')
            ->assertSee("value: '" . $super->id . "'", false)
            ->assertDontSee("value: '" . $otros->id . "'", false);
    }

    public function test_store_creates_only_selected_rows_with_chosen_category(): void
    {
        $account = $this->account();
        $super   = Category::create(['name' => 'Súper', 'kind' => 'expense']);

        $this->fakeVision([
            ['amount' => '4530.68', 'description' => 'Vallarta Satélite', 'date' => '2026-08-28', 'type' => 'expense', 'category' => null],
            ['amount' => '179.00',  'description' => 'Apple.com/bill',    'date' => '2026-08-24', 'type' => 'expense', 'category' => null],
        ]);

        $location = $this->upload($account)->headers->get('Location');
        $token    = basename($location);

        $this->post(route('accounts.import.store', [$account, $token]), ['rows' => [
            ['include' => 1, 'date' => '2026-08-28', 'description' => 'Vallarta Satélite', 'amount' => '4530.68', 'type' => 'expense', 'category_id' => $super->id],
            ['date' => '2026-08-24', 'description' => 'Apple.com/bill', 'amount' => '179.00', 'type' => 'expense', 'category_id' => ''],
        ]])->assertRedirect(route('accounts.show', $account));

        $this->assertSame(1, Transaction::count());

        $tx = Transaction::sole();
        $this->assertSame('4530.68', $tx->amount);
        $this->assertSame($account->id, $tx->account_id);
        $this->assertSame($super->id, $tx->category_id);
        $this->assertSame('expense', $tx->type);
        $this->assertSame('2026-08-28', $tx->date->toDateString());

        // El borrador se consume: reenviar ya no crea nada
        $this->post(route('accounts.import.store', [$account, $token]), ['rows' => [
            ['include' => 1, 'date' => '2026-08-24', 'description' => 'Apple.com/bill', 'amount' => '179.00', 'type' => 'expense'],
        ]])->assertRedirect(route('accounts.show', $account));

        $this->assertSame(1, Transaction::count());
    }

    public function test_existing_transaction_is_flagged_as_duplicate_and_unchecked(): void
    {
        $account = $this->account();

        Transaction::create([
            'date' => '2026-08-27', 'type' => 'expense', 'amount' => '179.00',
            'account_id' => $account->id, 'description' => 'Apple ya registrado',
        ]);

        $this->fakeVision([
            ['amount' => '179.00', 'description' => 'Apple.com/bill', 'date' => '2026-08-24', 'type' => 'expense', 'category' => null],
        ]);

        $location = $this->upload($account)->headers->get('Location');

        $this->get($location)
            ->assertOk()
            ->assertSee('Apple ya registrado')
            ->assertSee('parecen ya registrados');
    }

    public function test_overlapping_screenshots_are_deduplicated(): void
    {
        $account = $this->account();

        $this->fakeVision([
            ['amount' => '115.00', 'description' => 'Cinepolis', 'date' => '2026-08-15', 'type' => 'expense', 'category' => null],
        ]);

        $location = $this->upload($account, 2)->headers->get('Location');

        $this->get($location)->assertOk()->assertSee('Detecté <strong class="text-[#373737] dark:text-white">1</strong>', false);
    }

    private function revolut(): Account
    {
        return Account::create([
            'name' => 'Cheques', 'type' => 'debit', 'institution' => 'revolut',
            'initial_balance' => '0.00', 'color' => '#000000',
        ]);
    }

    public function test_deposit_already_in_other_account_is_offered_and_linked_as_transfer(): void
    {
        $cheques = $this->account();
        $revolut = $this->revolut();

        // Ya se importó de Revolut como abono suelto
        $deposit = Transaction::create([
            'date' => '2026-09-20', 'type' => 'income', 'amount' => '20000.00',
            'account_id' => $revolut->id, 'description' => 'Hans,hatch/dorantes',
        ]);

        $this->fakeVision([
            ['amount' => '20000.00', 'description' => 'Transferencia Interbancaria Ref Aut. 383450', 'date' => '2026-09-20', 'type' => 'expense', 'category' => null],
        ]);

        $location = $this->upload($cheques)->headers->get('Location');
        $token    = basename($location);

        $this->get($location)->assertOk()
            ->assertSee('parece transferencia')
            ->assertSee("type: 'transfer_out'", false)
            ->assertSee('name="rows[0][twin_id]" value="' . $deposit->id . '"', false);

        $this->post(route('accounts.import.store', [$cheques, $token]), ['rows' => [[
            'include' => 1, 'date' => '2026-09-20', 'description' => 'Transferencia Interbancaria Ref Aut. 383450',
            'amount' => '20,000.00', 'type' => 'transfer_out',
            'counterparty_account_id' => $revolut->id, 'twin_id' => $deposit->id,
        ]]])->assertRedirect(route('accounts.show', $cheques))
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'quedó como transferencia'));

        // No se duplicó: el abono de Revolut se convirtió en la transferencia
        $this->assertSame(1, Transaction::count());
        $tx = Transaction::sole();
        $this->assertSame('transfer', $tx->type);
        $this->assertSame($cheques->id, $tx->account_id);
        $this->assertSame($revolut->id, $tx->counterparty_account_id);
        $this->assertNull($tx->category_id);
    }

    public function test_transfer_already_registered_from_other_account_is_flagged_duplicate(): void
    {
        $cheques = $this->account();
        $revolut = $this->revolut();

        Transaction::create([
            'date' => '2026-09-20', 'type' => 'transfer', 'amount' => '700.00',
            'account_id' => $cheques->id, 'counterparty_account_id' => $revolut->id,
            'description' => 'Transferencia a Revolut',
        ]);

        $this->fakeVision([
            ['amount' => '700.00', 'description' => 'Transferencia Interbancaria', 'date' => '2026-09-20', 'type' => 'expense', 'category' => null],
        ]);

        // Desde Cheques (sale dinero): ya existe como transferencia
        $this->get($this->upload($cheques)->headers->get('Location'))->assertOk()
            ->assertSee('parecen ya registrados')
            ->assertSee('Transferencia Amex → Cheques');
    }

    public function test_transfer_already_registered_is_duplicate_from_receiving_side_too(): void
    {
        $cheques = $this->account();
        $revolut = $this->revolut();

        Transaction::create([
            'date' => '2026-09-20', 'type' => 'transfer', 'amount' => '700.00',
            'account_id' => $cheques->id, 'counterparty_account_id' => $revolut->id,
            'description' => 'Transferencia a Revolut',
        ]);

        $this->fakeVision([
            ['amount' => '700.00', 'description' => 'Hans,hatch/dorantes', 'date' => '2026-09-20', 'type' => 'income', 'category' => null],
        ]);

        // Desde Revolut (entra dinero): también se detecta
        $this->get($this->upload($revolut)->headers->get('Location'))->assertOk()->assertSee('parecen ya registrados');
    }

    public function test_new_incoming_transfer_without_twin_is_created_in_right_direction(): void
    {
        $revolut = $this->revolut();
        $cheques = $this->account();

        $this->fakeVision([
            ['amount' => '500.00', 'description' => 'Depósito', 'date' => '2026-09-20', 'type' => 'income', 'category' => null],
        ]);

        $token = basename($this->upload($revolut)->headers->get('Location'));

        $this->post(route('accounts.import.store', [$revolut, $token]), ['rows' => [[
            'include' => 1, 'date' => '2026-09-20', 'description' => 'Depósito',
            'amount' => '500.00', 'type' => 'transfer_in', 'counterparty_account_id' => $cheques->id,
        ]]])->assertRedirect(route('accounts.show', $revolut));

        $tx = Transaction::sole();
        $this->assertSame('transfer', $tx->type);
        $this->assertSame($cheques->id, $tx->account_id);
        $this->assertSame($revolut->id, $tx->counterparty_account_id);
    }

    public function test_transfer_without_counterparty_is_rejected(): void
    {
        $cheques = $this->account();

        $this->fakeVision([
            ['amount' => '500.00', 'description' => 'Traspaso', 'date' => '2026-09-20', 'type' => 'expense', 'category' => null],
        ]);

        $token = basename($this->upload($cheques)->headers->get('Location'));

        $this->post(route('accounts.import.store', [$cheques, $token]), ['rows' => [[
            'include' => 1, 'date' => '2026-09-20', 'description' => 'Traspaso',
            'amount' => '500.00', 'type' => 'transfer_out',
        ]]])->assertSessionHasErrors('rows.0.counterparty_account_id');

        $this->assertSame(0, Transaction::count());
    }

    public function test_upload_without_vision_key_redirects_with_message(): void
    {
        config(['services.openai.api_key' => null]);
        $account = $this->account();

        $this->actingAsVerified(User::factory()->create())
            ->post(route('accounts.import.upload', $account), ['images' => [UploadedFile::fake()->image('x.png')]])
            ->assertRedirect(route('accounts.show', $account))
            ->assertSessionHas('status');
    }
}
