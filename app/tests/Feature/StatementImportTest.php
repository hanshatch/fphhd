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
            ->assertSee('Transferencia Otra · Amex → Revolut · Cheques');
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

    /** Los tres seguros de Hans: recurrentes estimados vs. cargos reales en el estado de cuenta */
    private function seguros(Account $account): array
    {
        $cat = Category::create(['name' => 'Seguros', 'kind' => 'expense']);

        $make = fn (string $n, string $amount, string $due) => \App\Models\RecurringCharge::create([
            'name' => 'Seguros Monterrey Retiro - ' . $n, 'account_id' => $account->id, 'category_id' => $cat->id,
            'type' => 'expense', 'amount' => $amount, 'day_of_month' => (int) substr($due, -2),
            'start_date' => '2026-01-01', 'next_application_date' => $due, 'is_active' => true,
        ]);

        return [$make('1', '4717.63', '2026-09-28'), $make('2', '10499.37', '2026-09-28'), $make('3', '11019.03', '2026-09-29'), $cat];
    }

    private function segurosVision(): void
    {
        $this->fakeVision([
            ['amount' => '10519.54', 'description' => 'Domi 0004419763 Seguros Monterrey Ne', 'date' => '2026-09-29', 'type' => 'expense', 'category' => 'Otros gastos'],
            ['amount' => '11040.20', 'description' => 'Domi 0004419739 Seguros Monterrey Ne', 'date' => '2026-09-29', 'type' => 'expense', 'category' => 'Otros gastos'],
            ['amount' => '4726.35',  'description' => 'Domi 0004419374 Seguros Monterrey Ne', 'date' => '2026-09-29', 'type' => 'expense', 'category' => 'Otros gastos'],
        ]);
    }

    public function test_statement_rows_are_matched_to_pending_recurring_charges_by_closest_amount(): void
    {
        $account = $this->account();
        [$r1, $r2, $r3, $cat] = $this->seguros($account);
        $this->segurosVision();

        $location = $this->upload($account)->headers->get('Location');
        $token    = basename($location);

        $rows = \Illuminate\Support\Facades\Cache::get("statement_import:{$account->id}:{$token}");

        $this->assertSame($r2->id, $rows[0]['recurring']['id']);
        $this->assertSame($r3->id, $rows[1]['recurring']['id']);
        $this->assertSame($r1->id, $rows[2]['recurring']['id']);
        $this->assertSame('apply', $rows[0]['recurring']['mode']);
        $this->assertSame($cat->id, $rows[0]['category_id']);

        $this->get($location)->assertOk()
            ->assertSee('corresponden a cargos recurrentes')
            ->assertSee('Seguros Monterrey Retiro - 2');

        $this->post(route('accounts.import.store', [$account, $token]), ['rows' => [
            ['include' => 1, 'date' => '2026-09-29', 'description' => 'Domi 0004419763', 'amount' => '10,519.54', 'type' => 'expense', 'category_id' => $cat->id, 'recurring_id' => $r2->id],
            ['include' => 1, 'date' => '2026-09-29', 'description' => 'Domi 0004419739', 'amount' => '11,040.20', 'type' => 'expense', 'category_id' => $cat->id, 'recurring_id' => $r3->id],
            ['include' => 1, 'date' => '2026-09-29', 'description' => 'Domi 0004419374', 'amount' => '4,726.35',  'type' => 'expense', 'category_id' => $cat->id, 'recurring_id' => $r1->id],
        ]])->assertRedirect(route('accounts.show', $account))
            ->assertSessionHas('status', '3 cargos recurrentes aplicados con el monto real.');

        $this->assertSame(3, Transaction::count());
        $this->assertSame('10519.54', Transaction::where('description', 'Seguros Monterrey Retiro - 2')->sole()->amount);
        $this->assertSame('2026-10-28', $r2->fresh()->next_application_date->toDateString());
        $this->assertSame('2026-10-29', $r3->fresh()->next_application_date->toDateString());
        $this->assertSame(1, $r1->fresh()->applied_installments);
    }

    public function test_recurring_already_applied_with_estimate_is_adjusted_not_duplicated(): void
    {
        $account = $this->account();
        [, $r2, , $cat] = $this->seguros($account);

        // Hans ya lo aplicó con el monto estimado
        $estimated = app(\App\Services\RecurringChargeService::class)->applyCharge($r2, null, '2026-09-28');

        $this->fakeVision([
            ['amount' => '10519.54', 'description' => 'Domi 0004419763 Seguros Monterrey Ne', 'date' => '2026-09-29', 'type' => 'expense', 'category' => null],
        ]);

        $token = basename($this->upload($account)->headers->get('Location'));
        $rows  = \Illuminate\Support\Facades\Cache::get("statement_import:{$account->id}:{$token}");

        $this->assertSame('adjust', $rows[0]['recurring']['mode']);
        $this->assertSame($estimated->id, $rows[0]['recurring']['transaction_id']);

        $this->post(route('accounts.import.store', [$account, $token]), ['rows' => [[
            'include' => 1, 'date' => '2026-09-29', 'description' => 'Domi 0004419763', 'amount' => '10,519.54',
            'type' => 'expense', 'category_id' => $cat->id, 'adjust_tx_id' => $estimated->id,
        ]]])->assertSessionHas('status', '1 cargo recurrente ajustado al monto real.');

        $this->assertSame(1, Transaction::count());
        $this->assertSame('10519.54', $estimated->fresh()->amount);
        $this->assertSame('2026-09-29', $estimated->fresh()->date->toDateString());
    }

    public function test_amount_outside_tolerance_is_not_matched(): void
    {
        $account = $this->account();
        $this->seguros($account);

        $this->fakeVision([
            ['amount' => '15000.00', 'description' => 'Domi Seguros Monterrey Ne', 'date' => '2026-09-29', 'type' => 'expense', 'category' => null],
        ]);

        $token = basename($this->upload($account)->headers->get('Location'));
        $rows  = \Illuminate\Support\Facades\Cache::get("statement_import:{$account->id}:{$token}");

        $this->assertArrayNotHasKey('recurring', $rows[0]);
    }

    public function test_exact_amount_matches_recurring_even_if_bank_uses_another_name(): void
    {
        $account = $this->account();
        $icloud  = \App\Models\RecurringCharge::create([
            'name' => 'Servicio iCloud', 'account_id' => $account->id, 'type' => 'expense', 'amount' => '179.00',
            'day_of_month' => 24, 'start_date' => '2026-01-01', 'next_application_date' => '2026-09-24', 'is_active' => true,
        ]);

        $this->fakeVision([
            ['amount' => '179.00', 'description' => 'APPLE.COM/BILL CUPERTINO', 'date' => '2026-09-24', 'type' => 'expense', 'category' => null],
            ['amount' => '178.00', 'description' => 'OTRA COSA', 'date' => '2026-09-24', 'type' => 'expense', 'category' => null],
        ]);

        $token = basename($this->upload($account)->headers->get('Location'));
        $rows  = \Illuminate\Support\Facades\Cache::get("statement_import:{$account->id}:{$token}");

        $this->assertSame($icloud->id, $rows[0]['recurring']['id']);
        // Monto distinto y sin nombre en común: no se liga solo
        $this->assertArrayNotHasKey('recurring', $rows[1]);
    }

    public function test_manual_link_applies_recurring_and_learns_statement_text(): void
    {
        $account = $this->account();
        $gym     = \App\Models\RecurringCharge::create([
            'name' => 'Gimnasio', 'account_id' => $account->id, 'type' => 'expense', 'amount' => '500.00',
            'day_of_month' => 20, 'start_date' => '2026-01-01', 'next_application_date' => '2026-09-20', 'is_active' => true,
        ]);

        $this->fakeVision([
            ['amount' => '520.00', 'description' => 'SPORT CITY SATELITE 22', 'date' => '2026-09-20', 'type' => 'expense', 'category' => null],
        ]);

        $location = $this->upload($account)->headers->get('Location');
        $token    = basename($location);

        // Sin empate automático: la revisión ofrece ligarlo a mano
        $this->get($location)->assertOk()
            ->assertSee('No es un cargo recurrente')
            ->assertSee('Gimnasio · $500.00');

        $this->post(route('accounts.import.store', [$account, $token]), ['rows' => [[
            'include' => 1, 'date' => '2026-09-20', 'description' => 'SPORT CITY SATELITE 22',
            'amount' => '520.00', 'type' => 'expense', 'recurring_id' => $gym->id,
        ]]])->assertSessionHas('status', '1 cargo recurrente aplicado con el monto real.');

        $gym->refresh();
        $this->assertSame('sport city satelite', $gym->statement_text);
        $this->assertSame('2026-10-20', $gym->next_application_date->toDateString());
        $this->assertSame('520.00', Transaction::sole()->amount);

        // El mes siguiente lo reconoce solo por el texto aprendido, aunque el monto cambie un poco
        $match = app(\App\Services\RecurringMatchService::class)->assign($account, [0 => [
            'type' => 'expense', 'amount' => '510.00', 'date' => '2026-10-21', 'description' => 'SPORT CITY SATELITE 22',
        ]]);
        $this->assertSame($gym->id, $match[0]['charge']->id ?? null);
    }

    public function test_account_page_offers_multi_capture_modal(): void
    {
        $account = $this->account();

        $this->actingAsVerified(User::factory()->create())
            ->get(route('accounts.show', $account))
            ->assertOk()
            ->assertSee('Importar capturas')
            ->assertSee("importCaptures('" . route('accounts.import.upload', $account) . "')", false)
            ->assertSee('Agrega todas las capturas del periodo');
    }

    public function test_up_to_six_captures_are_accepted_and_a_seventh_is_rejected(): void
    {
        $account = $this->account();
        $this->fakeVision([
            ['amount' => '100.00', 'description' => 'Uno', 'date' => '2026-09-20', 'type' => 'expense', 'category' => null],
        ]);

        $this->upload($account, 6)->assertRedirect()->assertSessionHasNoErrors();

        $this->upload($account, 7)->assertSessionHasErrors('images');
    }

    /** Nu débito + dos cajitas, y la captura mezclada que muestra la app de Nu */
    private function nuSetup(): array
    {
        $debit = Account::create(['name' => 'Nu', 'type' => 'debit', 'institution' => 'nu', 'initial_balance' => '0.00', 'color' => '#820ad1']);
        $turbo = Account::create(['name' => 'Cajita Turbo', 'type' => 'investment', 'institution' => 'nu', 'initial_balance' => '0.00', 'color' => '#820ad1']);
        $gen   = Account::create(['name' => 'General', 'type' => 'investment', 'institution' => 'nu', 'initial_balance' => '0.00', 'color' => '#820ad1']);

        $this->fakeVision([
            ['amount' => '3903.30', 'description' => 'Agregaste dinero a tu Cajita · Cajita Turbo', 'date' => '2026-09-30', 'type' => 'income', 'category' => null],
            ['amount' => '4000.00', 'description' => 'transfer nu', 'date' => '2026-09-30', 'type' => 'income', 'category' => null],
            ['amount' => '200.00',  'description' => 'Telcel Amigo Sin Límite', 'date' => '2026-09-24', 'type' => 'expense', 'category' => null],
            ['amount' => '120.00',  'description' => 'Retiraste dinero de tu Cajita · Cajita Turbo', 'date' => '2026-09-24', 'type' => 'income', 'category' => null],
            ['amount' => '500.00',  'description' => 'Agregaste dinero a tu Cajita · General', 'date' => '2026-09-20', 'type' => 'income', 'category' => null],
        ]);

        return [$debit, $turbo, $gen];
    }

    public function test_nu_capture_uploaded_to_a_pocket_keeps_only_that_pockets_moves_as_transfers(): void
    {
        [$debit, $turbo] = $this->nuSetup();

        $token = basename($this->upload($turbo)->headers->get('Location'));
        $rows  = \Illuminate\Support\Facades\Cache::get("statement_import:{$turbo->id}:{$token}");

        // Solo los dos movimientos de Cajita Turbo; débito y la cajita General quedan fuera
        $this->assertCount(2, $rows);
        $this->assertSame(['3903.30', '120.00'], array_column($rows, 'amount'));
        $this->assertSame('transfer_in', $rows[0]['type']);   // agregaste → entra a la cajita
        $this->assertSame('transfer_out', $rows[1]['type']);  // retiraste → sale de la cajita
        $this->assertSame($debit->id, $rows[0]['counterparty_account_id']);

        $this->post(route('accounts.import.store', [$turbo, $token]), ['rows' => array_map(fn ($r) => [
            'include' => 1, 'date' => $r['date'], 'description' => $r['description'], 'amount' => $r['amount'],
            'type' => $r['type'], 'counterparty_account_id' => $r['counterparty_account_id'],
        ], $rows)])->assertRedirect(route('accounts.show', $turbo));

        $in  = Transaction::where('amount', '3903.30')->sole();
        $out = Transaction::where('amount', '120.00')->sole();
        $this->assertSame([$debit->id, $turbo->id], [$in->account_id, $in->counterparty_account_id]);
        $this->assertSame([$turbo->id, $debit->id], [$out->account_id, $out->counterparty_account_id]);
    }

    public function test_nu_capture_uploaded_to_debit_turns_pocket_moves_into_transfers_to_the_right_pocket(): void
    {
        [$debit, $turbo, $gen] = $this->nuSetup();

        $token = basename($this->upload($debit)->headers->get('Location'));
        $rows  = collect(\Illuminate\Support\Facades\Cache::get("statement_import:{$debit->id}:{$token}"))->keyBy('amount');

        $this->assertCount(5, $rows);
        $this->assertSame('transfer_out', $rows['3903.30']['type']);
        $this->assertSame($turbo->id, $rows['3903.30']['counterparty_account_id']);
        $this->assertSame('transfer_in', $rows['120.00']['type']);
        $this->assertSame('transfer_out', $rows['500.00']['type']);
        $this->assertSame($gen->id, $rows['500.00']['counterparty_account_id']);
        $this->assertSame('expense', $rows['200.00']['type']);
        $this->assertSame('income', $rows['4000.00']['type']);
    }

    public function test_pocket_move_already_registered_is_flagged_duplicate(): void
    {
        [$debit, $turbo] = $this->nuSetup();

        Transaction::create([
            'date' => '2026-09-30', 'type' => 'transfer', 'amount' => '3903.30',
            'account_id' => $debit->id, 'counterparty_account_id' => $turbo->id, 'description' => 'Agregaste dinero a tu Cajita',
        ]);

        $token = basename($this->upload($turbo)->headers->get('Location'));
        $rows  = \Illuminate\Support\Facades\Cache::get("statement_import:{$turbo->id}:{$token}");

        $this->assertNotNull($rows[0]['duplicate']);
        $this->assertNull($rows[1]['duplicate']);
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
