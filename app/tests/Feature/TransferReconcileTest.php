<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountService;
use App\Services\TransferReconcileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\WithTotpSession;

class TransferReconcileTest extends TestCase
{
    use RefreshDatabase, WithTotpSession;

    private function account(string $name, string $type = 'debit', string $initial = '10000.00'): Account
    {
        return Account::create([
            'name' => $name, 'type' => $type, 'institution' => 'other',
            'initial_balance' => $initial, 'color' => '#373737',
        ]);
    }

    private function pending(Account $account, string $direction, string $amount, string $date, string $description = 'SPEI'): Transaction
    {
        return Transaction::create([
            'date' => $date, 'type' => 'transfer', 'amount' => $amount, 'account_id' => $account->id,
            'transfer_pending' => $direction, 'description' => $description,
        ]);
    }

    private function balance(Account $account): string
    {
        return app(AccountService::class)->balance($account->fresh());
    }

    /** Respuestas del lector de imágenes, una por captura subida, en orden */
    private function vision(array ...$charges): void
    {
        config(['services.openai.api_key' => 'test-oa-key']);

        $sequence = Http::fakeSequence('api.openai.com/*');

        foreach ($charges as $charge) {
            $sequence->push(['choices' => [['message' => ['content' => json_encode(['charges' => [$charge]])]]]]);
        }
    }

    private function upload(Account $account): string
    {
        return $this->post(route('accounts.import.upload', $account), [
            'images' => [UploadedFile::fake()->image('edo.png', 800, 1200)],
        ])->headers->get('Location');
    }

    /** Sube una captura y guarda su renglón como transferencia sin la otra cuenta */
    private function importPending(Account $account, string $amount, string $date, string $rowType): void
    {
        $this->post(route('accounts.import.store', [$account, basename($this->upload($account))]), ['rows' => [[
            'include' => 1, 'date' => $date, 'description' => 'SPEI', 'amount' => $amount,
            'type' => $rowType, 'counterparty_account_id' => '',
        ]]])->assertRedirect(route('accounts.show', $account));
    }

    public function test_pending_transfers_move_the_balance_in_their_own_direction(): void
    {
        $cheques = $this->account('Cheques');
        $nu      = $this->account('Nu');
        $tdc     = $this->account('Conquista', 'credit', '5000.00');

        $this->pending($cheques, 'out', '3000.00', '2026-11-03');
        $this->pending($nu, 'in', '3000.00', '2026-11-03');
        $this->pending($tdc, 'in', '1000.00', '2026-11-03'); // pago recibido en la tarjeta

        $this->assertSame('7000.00', $this->balance($cheques));
        $this->assertSame('13000.00', $this->balance($nu));
        $this->assertSame('4000.00', $this->balance($tdc));

        $all = app(AccountService::class)->balances(Account::all());
        $this->assertSame(['7000.00', '13000.00', '4000.00'], [$all[$cheques->id], $all[$nu->id], $all[$tdc->id]]);

        [, $running] = app(AccountService::class)->runningBalances($nu->fresh());
        $this->assertContains('13000.00', array_values($running));
    }

    public function test_importing_both_sides_reconciles_them_into_one_transfer(): void
    {
        $this->actingAsVerified(User::factory()->create());
        $cheques = $this->account('Cheques');
        $nu      = $this->account('Nu');

        $this->vision(
            ['amount' => '20000.00', 'description' => 'SPEI', 'date' => '2026-11-03', 'type' => 'expense', 'category' => null],
            ['amount' => '20000.00', 'description' => 'Depósito SPEI', 'date' => '2026-11-04', 'type' => 'income', 'category' => null],
        );

        $this->importPending($cheques, '20000.00', '2026-11-03', 'transfer_out');

        $this->assertSame(1, Transaction::pendingTransfers()->count());
        $this->assertSame('-10000.00', $this->balance($cheques));

        // Al cargar la otra cuenta, el renglón ya viene propuesto como transferencia desde Cheques
        $location = $this->upload($nu);

        $this->get($location)->assertOk()->assertSee('El otro lado ya está en');

        $pendingId = Transaction::pendingTransfers()->value('id');

        $this->post(route('accounts.import.store', [$nu, basename($location)]), ['rows' => [[
            'include' => 1, 'date' => '2026-11-04', 'description' => 'Depósito SPEI', 'amount' => '20000.00',
            'type' => 'transfer_in', 'counterparty_account_id' => $cheques->id, 'twin_id' => $pendingId,
        ]]]);

        $tx = Transaction::sole();
        $this->assertSame('transfer', $tx->type);
        $this->assertNull($tx->transfer_pending);
        $this->assertSame([$cheques->id, $nu->id], [$tx->account_id, $tx->counterparty_account_id]);
        $this->assertSame('-10000.00', $this->balance($cheques));
        $this->assertSame('30000.00', $this->balance($nu));
    }

    public function test_two_pending_sides_are_matched_automatically_after_import(): void
    {
        $this->actingAsVerified(User::factory()->create());
        $cheques = $this->account('Cheques');
        $nu      = $this->account('Nu');

        // La entrada se cargó primero, sin saber de dónde venía
        $this->pending($nu, 'in', '4500.00', '2026-11-05', 'Depósito');

        $this->vision(['amount' => '4500.00', 'description' => 'SPEI', 'date' => '2026-11-04', 'type' => 'expense', 'category' => null]);
        $this->importPending($cheques, '4500.00', '2026-11-04', 'transfer_out');

        $tx = Transaction::sole();
        $this->assertNull($tx->transfer_pending);
        $this->assertSame([$cheques->id, $nu->id], [$tx->account_id, $tx->counterparty_account_id]);
        $this->assertSame('5500.00', $this->balance($cheques));
        $this->assertSame('14500.00', $this->balance($nu));
    }

    public function test_ambiguous_pairs_are_left_for_the_screen_and_resolved_there(): void
    {
        $this->actingAsVerified(User::factory()->create());
        $cheques = $this->account('Cheques');
        $nu      = $this->account('Nu');
        $klar    = $this->account('Klar');

        $out = $this->pending($cheques, 'out', '1000.00', '2026-11-03');
        $in1 = $this->pending($nu, 'in', '1000.00', '2026-11-03');
        $in2 = $this->pending($klar, 'in', '1000.00', '2026-11-04');

        $this->assertSame(0, app(TransferReconcileService::class)->autoMatch());
        $this->assertSame(3, Transaction::count());

        $this->get(route('transfers.pending'))->assertOk()
            ->assertSee('Transferencias por conciliar')->assertSee('Posibles otros lados');
        $this->get(route('dashboard'))->assertOk()->assertSee('3 transferencias por conciliar');

        $this->post(route('transfers.pending.resolve', $out), ['match_id' => $in2->id])->assertRedirect();

        $this->assertNull($out->fresh()->transfer_pending);
        $this->assertSame($klar->id, $out->fresh()->counterparty_account_id);
        $this->assertNull(Transaction::find($in2->id));
        $this->assertTrue($in1->fresh()->isPendingTransfer());
    }

    public function test_pending_can_be_assigned_to_an_account_or_turned_into_a_movement(): void
    {
        $this->actingAsVerified(User::factory()->create());
        $cheques = $this->account('Cheques');
        $nu      = $this->account('Nu');

        // Entró a Nu: al asignar Cheques, queda Cheques → Nu
        $in = $this->pending($nu, 'in', '800.00', '2026-11-03');
        $this->post(route('transfers.pending.resolve', $in), ['account_id' => $cheques->id]);

        $in->refresh();
        $this->assertSame([$cheques->id, $nu->id, null], [$in->account_id, $in->counterparty_account_id, $in->transfer_pending]);
        $this->assertSame('9200.00', $this->balance($cheques));
        $this->assertSame('10800.00', $this->balance($nu));

        // No era entre cuentas propias: pasa a gasto y entra a reportes
        $out = $this->pending($cheques, 'out', '300.00', '2026-11-06', 'SPEI a Juan');
        $this->post(route('transfers.pending.resolve', $out), ['as_movement' => 1]);

        $out->refresh();
        $this->assertSame(['expense', null], [$out->type, $out->transfer_pending]);
        $this->assertSame('8900.00', $this->balance($cheques));
    }

    public function test_loose_income_in_another_account_can_be_chosen_as_the_other_side(): void
    {
        $this->actingAsVerified(User::factory()->create());
        $cheques = $this->account('Cheques');
        $nu      = $this->account('Nu');

        $out    = $this->pending($cheques, 'out', '2500.00', '2026-11-03');
        $income = Transaction::create(['date' => '2026-11-03', 'type' => 'income', 'amount' => '2500.00', 'account_id' => $nu->id, 'description' => 'Depósito']);

        $this->get(route('transfers.pending'))->assertOk()->assertSee('registrado como ingreso');
        $this->post(route('transfers.pending.resolve', $out), ['match_id' => $income->id]);

        $tx = Transaction::sole();
        $this->assertSame([$cheques->id, $nu->id, 'transfer'], [$tx->account_id, $tx->counterparty_account_id, $tx->type]);
        $this->assertSame('12500.00', $this->balance($nu));
    }
}
