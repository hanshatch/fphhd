<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferDescriptionTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $name): Account
    {
        return Account::create([
            'name'            => $name,
            'type'            => 'debit',
            'institution'     => 'banamex',
            'initial_balance' => '1000.00',
            'color'           => '#76a72b',
        ]);
    }

    private function transfer(array $extra = []): Transaction
    {
        return Transaction::create(array_merge([
            'date'                    => now()->toDateString(),
            'type'                    => 'transfer',
            'amount'                  => '500.00',
            'account_id'              => $this->account('Origen')->id,
            'counterparty_account_id' => $this->account('Destino')->id,
        ], $extra));
    }

    public function test_una_transferencia_sin_descripcion_se_etiqueta_sola(): void
    {
        $this->assertSame('Transferencia entre cuentas', $this->transfer()->description);
    }

    public function test_la_descripcion_vacia_tambien_se_rellena(): void
    {
        $this->assertSame('Transferencia entre cuentas', $this->transfer(['description' => '   '])->description);
    }

    public function test_una_descripcion_propia_se_respeta(): void
    {
        $this->assertSame('Pago TDC', $this->transfer(['description' => 'Pago TDC'])->description);
    }

    public function test_los_otros_tipos_no_se_tocan(): void
    {
        $gasto = Transaction::create([
            'date'       => now()->toDateString(),
            'type'       => 'expense',
            'amount'     => '100.00',
            'account_id' => $this->account('Débito')->id,
        ]);

        $this->assertNull($gasto->description);
    }

    public function test_al_cambiar_un_movimiento_a_transferencia_tambien_aplica(): void
    {
        $gasto = Transaction::create([
            'date'       => now()->toDateString(),
            'type'       => 'expense',
            'amount'     => '100.00',
            'account_id' => $this->account('Débito')->id,
        ]);

        $gasto->update([
            'type'                    => 'transfer',
            'counterparty_account_id' => $this->account('Destino')->id,
        ]);

        $this->assertSame('Transferencia entre cuentas', $gasto->fresh()->description);
    }
}
