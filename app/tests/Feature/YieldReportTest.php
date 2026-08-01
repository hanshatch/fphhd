<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\YieldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTotpSession;

class YieldReportTest extends TestCase
{
    use RefreshDatabase, WithTotpSession;

    private function savingsAccount(string $initial, ?string $apr = null): Account
    {
        return Account::create([
            'name'            => 'Sofipo ' . uniqid(),
            'type'            => 'savings',
            'institution'     => 'nu',
            'initial_balance' => $initial,
            'invest_apr'      => $apr,
            'color'           => '#76a72b',
        ]);
    }

    private function interest(Account $account, string $amount, string $date): Transaction
    {
        return Transaction::create([
            'date'       => $date,
            'type'       => 'interest',
            'amount'     => $amount,
            'account_id' => $account->id,
        ]);
    }

    // ── APR efectivo: interés observado sobre saldo promedio, anualizado ──

    public function test_effective_apr_is_annualized_interest_over_average_balance(): void
    {
        // Saldo constante de 10,000 desde antes de la ventana; interés
        // del mes anterior de 100 → APR de ese mes = 100/10000*1200 = 12%
        $account = $this->savingsAccount('10000.00', '13.00');
        $prev    = now()->subMonthNoOverflow();

        $this->interest($account, '100.00', $prev->copy()->startOfMonth()->toDateString());

        $report = app(YieldService::class)->report(12);
        $row    = $report['yieldRows']->firstWhere(fn ($r) => $r['account']->id === $account->id);

        $monthly = $row['monthly'][$prev->format('Y-m')];

        // El saldo promedio del mes incluye el interés abonado a mitad de camino:
        // (10000 + 10100)/2 = 10050 → APR = 100/10050*1200 = 11.94%
        $this->assertSame('10050.00', $monthly['avg']);
        $this->assertSame('11.94', $monthly['apr']);
        $this->assertSame('100.00', $row['interest_prev']);
        $this->assertFalse($row['pending']);
    }

    public function test_account_without_recent_interest_is_pending(): void
    {
        $stale = $this->savingsAccount('5000.00', '15.00');
        $fresh = $this->savingsAccount('5000.00', '13.00');
        $never = $this->savingsAccount('5000.00');

        // Captura vieja: anterior al inicio del mes pasado
        $this->interest($stale, '50.00', now()->subMonthsNoOverflow(3)->toDateString());
        // Captura reciente: dentro del mes pasado
        $this->interest($fresh, '55.00', now()->subMonthNoOverflow()->endOfMonth()->toDateString());

        $pending = app(YieldService::class)->pendingCaptures();
        $ids     = $pending->pluck('account.id');

        $this->assertTrue($ids->contains($stale->id));
        $this->assertTrue($ids->contains($never->id));
        $this->assertFalse($ids->contains($fresh->id));
    }

    public function test_credit_and_debit_accounts_are_excluded_from_yields(): void
    {
        Account::create([
            'name'            => 'Débito',
            'type'            => 'debit',
            'institution'     => 'banamex',
            'initial_balance' => '1000.00',
            'color'           => '#373737',
        ]);

        $report = app(YieldService::class)->report(12);

        $this->assertTrue($report['yieldRows']->isEmpty());
        $this->assertSame('0.00', $report['yieldTotal']);
    }

    // ── Tope del APR ────────────────────────────────────────────────────

    public function test_el_tope_se_expone_como_dato_informativo(): void
    {
        $account = $this->savingsAccount('50000.00', '13.00');
        $account->update(['invest_cap' => '25000.00']);

        $row = app(YieldService::class)->report(12)['yieldRows']
            ->firstWhere(fn ($r) => $r['account']->id === $account->id);

        // Tasa y tope se muestran tal cual: el sistema no deriva cálculos de ellos
        $this->assertSame('25000.00', $row['apr_cap']);
        $this->assertSame('13.00', $row['apr_nominal']);
        $this->assertArrayNotHasKey('apr_expected', $row);
    }

    public function test_el_tope_se_guarda_desde_el_formulario(): void
    {
        $user    = User::factory()->create();
        $account = $this->savingsAccount('10000.00', '13.00');

        $this->actingAsVerified($user)->patch(route('accounts.update', $account), [
            'name'            => $account->name,
            'type'            => 'investment',
            'institution'     => 'nu',
            'initial_balance' => '10,000.00',
            'color'           => '#76a72b',
            'invest_apr'      => '13.00',
            'invest_cap'      => '25,000.00',
        ])->assertRedirect();

        $this->assertSame('25000.00', $account->fresh()->invest_cap);
    }

    public function test_yields_report_page_renders(): void
    {
        $user    = User::factory()->create();
        $account = $this->savingsAccount('10000.00', '14.50');
        $this->interest($account, '120.00', now()->subMonthNoOverflow()->toDateString());

        $this->actingAsVerified($user)
            ->get('/reports?type=yields')
            ->assertOk()
            ->assertSee($account->name)
            ->assertSee('APR efectivo');
    }
}
