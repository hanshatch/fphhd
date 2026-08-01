<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTotpSession;

class TransactionReorderTest extends TestCase
{
    use RefreshDatabase, WithTotpSession;

    private function account(): Account
    {
        return Account::create([
            'name'            => 'Débito',
            'type'            => 'debit',
            'institution'     => 'banamex',
            'initial_balance' => '1000.00',
            'color'           => '#76a72b',
        ]);
    }

    private function tx(Account $account, string $date, string $amount): Transaction
    {
        return Transaction::create([
            'date'        => $date,
            'type'        => 'expense',
            'amount'      => $amount,
            'account_id'  => $account->id,
            'description' => 'Gasto ' . $amount,
        ]);
    }

    public function test_reordena_los_movimientos_del_mismo_dia(): void
    {
        $user    = User::factory()->create();
        $account = $this->account();

        $a = $this->tx($account, '2026-07-30', '100.00');
        $b = $this->tx($account, '2026-07-30', '200.00');
        $c = $this->tx($account, '2026-07-30', '300.00');

        $this->actingAsVerified($user)
            ->postJson(route('transactions.reorder'), ['ids' => [$c->id, $a->id, $b->id]])
            ->assertOk();

        $this->assertSame(1, $c->fresh()->position);
        $this->assertSame(2, $a->fresh()->position);
        $this->assertSame(3, $b->fresh()->position);
    }

    public function test_rechaza_ids_de_dias_distintos(): void
    {
        $user    = User::factory()->create();
        $account = $this->account();

        $a = $this->tx($account, '2026-07-30', '100.00');
        $b = $this->tx($account, '2026-07-29', '200.00');

        $this->actingAsVerified($user)
            ->postJson(route('transactions.reorder'), ['ids' => [$b->id, $a->id]])
            ->assertStatus(422);

        $this->assertSame(0, $a->fresh()->position);
        $this->assertSame(0, $b->fresh()->position);
    }

    public function test_el_orden_manual_manda_en_la_vista_y_el_saldo_corrido_lo_respeta(): void
    {
        $user    = User::factory()->create();
        $account = $this->account();

        $a = $this->tx($account, '2026-07-30', '100.00');
        $b = $this->tx($account, '2026-07-30', '200.00');

        // Por defecto (position 0) la lista muestra el más nuevo arriba: b, a
        [$txs] = app(AccountService::class)->runningBalances($account);
        $this->assertSame([$a->id, $b->id], $txs->pluck('id')->all()); // cronológico

        // Se acomoda a mano: a arriba, b abajo
        $this->actingAsVerified($user)
            ->postJson(route('transactions.reorder'), ['ids' => [$a->id, $b->id]])
            ->assertOk();

        [$txs, $balances] = app(AccountService::class)->runningBalances($account->fresh());

        // Cronológico ahora es el inverso de la pantalla: b (abajo), a (arriba)
        $this->assertSame([$b->id, $a->id], $txs->pluck('id')->all());

        // El saldo corrido acumula en ese orden: 1000 - 200 = 800, luego -100 = 700
        $this->assertSame('800.00', $balances[$b->id]);
        $this->assertSame('700.00', $balances[$a->id]);
    }

    public function test_la_vista_de_cuenta_agrupa_por_dia_y_muestra_la_agarradera(): void
    {
        $user    = User::factory()->create();
        $account = $this->account();

        $this->tx($account, '2026-07-30', '100.00');
        $this->tx($account, '2026-07-30', '200.00');
        $solo = $this->tx($account, '2026-07-29', '300.00');

        $html = $this->actingAsVerified($user)->get(route('accounts.show', $account))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-date="2026-07-30"', $html);
        $this->assertStringContainsString('data-drag-handle', $html);

        // Un día con un solo movimiento no lleva agarradera: 2 botones, no 3
        // (se cuenta el atributo del botón, no el selector que usa el script)
        $this->assertSame(2, substr_count($html, 'data-drag-handle data-no-spinner'));
        $this->assertStringContainsString('data-id="' . $solo->id . '"', $html);
    }

    public function test_requiere_sesion(): void
    {
        $account = $this->account();
        $a       = $this->tx($account, '2026-07-30', '100.00');

        $this->postJson(route('transactions.reorder'), ['ids' => [$a->id]])
            ->assertStatus(401);
    }
}
