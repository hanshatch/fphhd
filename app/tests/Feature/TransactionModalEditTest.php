<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTotpSession;

class TransactionModalEditTest extends TestCase
{
    use RefreshDatabase, WithTotpSession;

    private function account(string $name = 'Débito'): Account
    {
        return Account::create([
            'name'            => $name,
            'type'            => 'debit',
            'institution'     => 'banamex',
            'initial_balance' => '0.00',
            'color'           => '#76a72b',
        ]);
    }

    private function transaction(Account $account): Transaction
    {
        return Transaction::create([
            'date'        => now()->toDateString(),
            'type'        => 'expense',
            'amount'      => '399.60',
            'account_id'  => $account->id,
            'description' => 'Café',
        ]);
    }

    public function test_el_fragmento_del_modal_trae_el_formulario_sin_layout(): void
    {
        $user    = User::factory()->create();
        $account = $this->account();
        $tx      = $this->transaction($account);

        $response = $this->actingAsVerified($user)
            ->get(route('transactions.edit.modal', $tx) . '?redirect_to=' . urlencode('/accounts/' . $account->id));

        $response->assertOk();
        $response->assertSee('name="redirect_to" value="/accounts/' . $account->id . '"', false);
        $response->assertSee('Guardar cambios');
        $response->assertDontSee('<html', false);
    }

    public function test_guardar_desde_el_modal_regresa_a_la_cuenta(): void
    {
        $user    = User::factory()->create();
        $account = $this->account();
        $tx      = $this->transaction($account);

        $response = $this->actingAsVerified($user)
            ->patch(route('transactions.update', $tx), [
                'date'        => $tx->date->toDateString(),
                'type'        => 'expense',
                'amount'      => '450.00',
                'account_id'  => $account->id,
                'description' => 'Café ajustado',
                'redirect_to' => '/accounts/' . $account->id,
            ]);

        $response->assertRedirect('/accounts/' . $account->id);
        $this->assertSame('450.00', $tx->fresh()->amount);
    }

    public function test_redirect_to_externo_se_ignora(): void
    {
        $user    = User::factory()->create();
        $account = $this->account();
        $tx      = $this->transaction($account);

        $response = $this->actingAsVerified($user)
            ->patch(route('transactions.update', $tx), [
                'date'        => $tx->date->toDateString(),
                'type'        => 'expense',
                'amount'      => '450.00',
                'account_id'  => $account->id,
                'redirect_to' => 'https://evil.example/phishing',
            ]);

        $response->assertRedirect(route('transactions.index'));
    }

    public function test_eliminar_desde_la_cuenta_no_saca_a_movimientos(): void
    {
        $user    = User::factory()->create();
        $account = $this->account();
        $tx      = $this->transaction($account);

        $response = $this->actingAsVerified($user)
            ->delete(route('transactions.destroy', $tx), ['redirect_to' => '/accounts/' . $account->id]);

        $response->assertRedirect('/accounts/' . $account->id);
        $this->assertNull(Transaction::find($tx->id));
    }

    public function test_el_modal_de_nuevo_movimiento_precarga_la_cuenta(): void
    {
        $user    = User::factory()->create();
        $account = $this->account();

        $response = $this->actingAsVerified($user)
            ->get(route('transactions.create.modal', [
                'account_id'  => $account->id,
                'redirect_to' => '/accounts/' . $account->id,
            ]));

        $response->assertOk();
        $response->assertSee('value="' . $account->id . '" selected', false);
        $response->assertSee('Registrar');
        $response->assertDontSee('<html', false);
    }

    public function test_registrar_desde_el_modal_regresa_a_la_cuenta(): void
    {
        $user    = User::factory()->create();
        $account = $this->account();

        $response = $this->actingAsVerified($user)
            ->post(route('transactions.store'), [
                'date'        => now()->toDateString(),
                'type'        => 'expense',
                'amount'      => '120.50',
                'account_id'  => $account->id,
                'description' => 'Taxi',
                'redirect_to' => '/accounts/' . $account->id,
            ]);

        $response->assertRedirect('/accounts/' . $account->id);
        $this->assertSame('120.50', Transaction::latest('id')->first()->amount);
    }

    public function test_registrar_y_agregar_otro_reabre_el_modal_en_la_cuenta(): void
    {
        $user    = User::factory()->create();
        $account = $this->account();

        $response = $this->actingAsVerified($user)
            ->post(route('transactions.store'), [
                'date'         => now()->toDateString(),
                'type'         => 'expense',
                'amount'       => '80.00',
                'account_id'   => $account->id,
                'redirect_to'  => '/accounts/' . $account->id,
                'save_and_new' => '1',
            ]);

        $response->assertRedirect('/accounts/' . $account->id . '?new=1');
    }

    public function test_duplicar_desde_la_cuenta_vuelve_con_el_modal_abierto(): void
    {
        $user    = User::factory()->create();
        $account = $this->account();
        $tx      = $this->transaction($account);

        $response = $this->actingAsVerified($user)
            ->post(route('transactions.duplicate', $tx), ['redirect_to' => '/accounts/' . $account->id]);

        $copy = Transaction::latest('id')->first();

        $response->assertRedirect('/accounts/' . $account->id . '?edit=' . $copy->id);
    }
}
