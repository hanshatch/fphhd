<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountService;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTotpSession;

class FinanceRulesTest extends TestCase
{
    use RefreshDatabase, WithTotpSession;

    private function debitAccount(string $initial = '0.00'): Account
    {
        return Account::create([
            'name'            => 'Débito ' . uniqid(),
            'type'            => 'debit',
            'institution'     => 'banamex',
            'initial_balance' => $initial,
            'color'           => '#76a72b',
        ]);
    }

    private function creditAccount(): Account
    {
        return Account::create([
            'name'            => 'TDC ' . uniqid(),
            'type'            => 'credit',
            'institution'     => 'other',
            'initial_balance' => '0.00',
            'color'           => '#373737',
        ]);
    }

    private function tx(Account $account, string $type, string $amount, array $extra = []): Transaction
    {
        return Transaction::create(array_merge([
            'date'       => now()->toDateString(),
            'type'       => $type,
            'amount'     => $amount,
            'account_id' => $account->id,
        ], $extra));
    }

    // ── Saldos (regla 4.2: transferencias afectan ambas cuentas) ──────────

    public function test_debit_balance_subtracts_expenses_and_outgoing_transfers(): void
    {
        $a = $this->debitAccount('1000.00');
        $b = $this->debitAccount('0.00');

        $this->tx($a, 'income', '500.00');
        $this->tx($a, 'expense', '200.00');
        $this->tx($a, 'transfer', '300.00', ['counterparty_account_id' => $b->id]);

        $service = app(AccountService::class);

        $this->assertSame('1000.00', $service->balance($a)); // 1000 + 500 - 200 - 300
        $this->assertSame('300.00', $service->balance($b));  // transferencia entrante
    }

    public function test_credit_balance_expenses_raise_debt_and_payments_lower_it(): void
    {
        $tdc   = $this->creditAccount();
        $debit = $this->debitAccount('5000.00');

        $this->tx($tdc, 'expense', '2000.00');
        $this->tx($debit, 'transfer', '1500.00', ['counterparty_account_id' => $tdc->id]);

        $service = app(AccountService::class);

        $this->assertSame('500.00', $service->balance($tdc));   // 2000 - 1500
        $this->assertSame('3500.00', $service->balance($debit)); // 5000 - 1500
    }

    public function test_credit_balance_refunds_lower_debt(): void
    {
        $tdc = $this->creditAccount();

        $this->tx($tdc, 'expense', '2000.00');
        $this->tx($tdc, 'income', '2000.00'); // reembolso de la tienda

        $this->assertSame('0.00', app(AccountService::class)->balance($tdc));
    }

    // ── Regla 4.3: intereses fuera del ingreso operativo ──────────────────

    public function test_monthly_flow_excludes_interest_from_operating_income(): void
    {
        $a = $this->debitAccount();

        $this->tx($a, 'income', '30000.00');
        $this->tx($a, 'interest', '450.00');
        $this->tx($a, 'expense', '1000.00');

        $flow = app(DashboardService::class)->monthlyFlow();

        $this->assertSame('30000.00', $flow['income']);
        $this->assertSame('1000.00', $flow['expenses']);
    }

    public function test_transfers_do_not_count_as_income_or_expense(): void
    {
        $a = $this->debitAccount('1000.00');
        $b = $this->debitAccount();

        $this->tx($a, 'transfer', '500.00', ['counterparty_account_id' => $b->id]);

        $flow = app(DashboardService::class)->monthlyFlow();

        $this->assertSame('0.00', $flow['income']);
        $this->assertSame('0.00', $flow['expenses']);
    }

    public function test_annual_report_excludes_interest(): void
    {
        $user = User::factory()->create();
        $a    = $this->debitAccount();

        $this->tx($a, 'income', '10000.00');
        $this->tx($a, 'interest', '500.00');

        $response = $this->actingAsVerified($user)
            ->get('/reports?type=annual&year=' . now()->year);

        $response->assertOk();
        $this->assertEquals(10000.00, $response->viewData('totalIncome'));
    }

    // ── Integridad de transferencias ───────────────────────────────────────

    public function test_transfer_requires_counterparty_account(): void
    {
        $user = User::factory()->create();
        $a    = $this->debitAccount();

        $response = $this->actingAsVerified($user)->post('/transactions', [
            'date'       => now()->toDateString(),
            'type'       => 'transfer',
            'amount'     => '500.00',
            'account_id' => $a->id,
        ]);

        $response->assertSessionHasErrors('counterparty_account_id');
        $this->assertSame(0, Transaction::count());
    }

    public function test_account_show_includes_incoming_transfers_in_running_balance(): void
    {
        $user = User::factory()->create();
        $a    = $this->debitAccount('1000.00');
        $b    = $this->debitAccount('0.00');

        $transfer = $this->tx($a, 'transfer', '300.00', ['counterparty_account_id' => $b->id]);

        $response = $this->actingAsVerified($user)->get(route('accounts.show', $b));

        $response->assertOk();

        $runningBalances = $response->viewData('runningBalances');
        $this->assertArrayHasKey($transfer->id, $runningBalances);
        $this->assertSame('300.00', $runningBalances[$transfer->id]);
    }

    // ── Ajuste de saldo ─────────────────────────────────────────────────────

    public function test_adjust_balance_on_debit_account(): void
    {
        $user = User::factory()->create();
        $a    = $this->debitAccount('1000.00');

        $this->actingAsVerified($user)->post(route('accounts.adjust.store', $a), [
            'target_balance' => '800.00',
            'date'           => now()->toDateString(),
        ]);

        $this->assertSame('800.00', app(AccountService::class)->balance($a->fresh()));
    }

    public function test_adjust_balance_on_credit_account_lowers_debt(): void
    {
        $user = User::factory()->create();
        $tdc  = $this->creditAccount();

        $this->tx($tdc, 'expense', '1000.00');

        // La deuda real es 800, no 1000
        $this->actingAsVerified($user)->post(route('accounts.adjust.store', $tdc), [
            'target_balance' => '800.00',
            'date'           => now()->toDateString(),
        ]);

        $this->assertSame('800.00', app(AccountService::class)->balance($tdc->fresh()));
    }
}
