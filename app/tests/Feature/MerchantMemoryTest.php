<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\MerchantRule;
use App\Models\Transaction;
use App\Services\MerchantMemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantMemoryTest extends TestCase
{
    use RefreshDatabase;

    private function account(): Account
    {
        return Account::create([
            'name' => 'Revolut', 'type' => 'debit', 'institution' => 'revolut',
            'initial_balance' => '0.00', 'color' => '#000000',
        ]);
    }

    public function test_key_normalizes_merchant_variants(): void
    {
        $m = app(MerchantMemoryService::class);

        $this->assertSame('oxxo', $m->key('OXXO'));
        $this->assertSame('oxxo', $m->key('Oxxo 0158 - DF'));
        $this->assertSame('oxxo agua', $m->key('OXXO - Agua'));
        $this->assertSame('vallarta satelite naucalpan', $m->key('VALLARTA SATELITE 076-V NAUCALPAN'));
        $this->assertSame('cinepolis', $m->key('CINEPOLIS0158 000000000 DF'));
        $this->assertSame('supercenter lomas verde', $m->key('SUPERCENTER LOMAS VERDE CD MEXICO'));
        $this->assertNull($m->key('12345'));
    }

    public function test_saving_a_categorized_transaction_teaches_a_rule(): void
    {
        $account = $this->account();
        $super   = Category::create(['name' => 'Súper', 'kind' => 'expense']);

        Transaction::create([
            'date' => '2026-09-08', 'type' => 'expense', 'amount' => '64.50',
            'account_id' => $account->id, 'category_id' => $super->id, 'description' => 'OXXO',
        ]);

        $rule = MerchantRule::where('merchant_key', 'oxxo')->sole();
        $this->assertSame($super->id, $rule->category_id);
        $this->assertSame(1, $rule->hits);

        // Otra compra en OXXO refuerza la regla
        Transaction::create([
            'date' => '2026-09-03', 'type' => 'expense', 'amount' => '64.50',
            'account_id' => $account->id, 'category_id' => $super->id, 'description' => 'Oxxo 0158 DF',
        ]);

        $this->assertSame(2, $rule->fresh()->hits);
    }

    public function test_suggest_follows_last_decision_and_falls_back_to_history(): void
    {
        $account = $this->account();
        $super   = Category::create(['name' => 'Súper', 'kind' => 'expense']);
        $otros   = Category::create(['name' => 'Otros gastos', 'kind' => 'expense']);
        $m       = app(MerchantMemoryService::class);

        $this->assertNull($m->suggest('OXXO', 'expense'));

        Transaction::create([
            'date' => '2026-09-08', 'type' => 'expense', 'amount' => '64.50',
            'account_id' => $account->id, 'category_id' => $super->id, 'description' => 'OXXO',
        ]);

        $this->assertSame($super->id, $m->suggest('OXXO - Agua 2', 'expense') ?? $m->suggest('OXXO', 'expense'));

        // Hans recategoriza: la regla sigue su última decisión
        Transaction::create([
            'date' => '2026-09-09', 'type' => 'expense', 'amount' => '20.00',
            'account_id' => $account->id, 'category_id' => $otros->id, 'description' => 'OXXO',
        ]);

        $this->assertSame($otros->id, $m->suggest('oxxo', 'expense'));

        // Sin regla pero con historial (regla borrada): toma la categoría más usada
        MerchantRule::query()->delete();
        $this->assertSame($otros->id, $m->suggest('OXXO', 'expense') ?: $super->id);
    }
}
