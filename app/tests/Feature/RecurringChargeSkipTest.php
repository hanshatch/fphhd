<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\RecurringCharge;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTotpSession;

class RecurringChargeSkipTest extends TestCase
{
    use RefreshDatabase, WithTotpSession;

    private function charge(array $extra = []): RecurringCharge
    {
        $account = Account::create([
            'name'            => 'Cheques',
            'type'            => 'debit',
            'institution'     => 'banamex',
            'initial_balance' => '0.00',
            'color'           => '#76a72b',
        ]);

        return RecurringCharge::create(array_merge([
            'name'                  => 'Mesada Henri',
            'account_id'            => $account->id,
            'type'                  => 'expense',
            'amount'                => '1500.00',
            'day_of_month'          => 1,
            'start_date'            => '2026-01-01',
            'next_application_date' => '2026-09-01',
            'is_active'             => true,
        ], $extra));
    }

    public function test_index_separates_overdue_upcoming_applied_and_skipped_of_the_month(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-11-16 10:00:00');

        $account = \App\Models\Account::create(['name' => 'Conquista', 'type' => 'credit', 'institution' => 'banamex', 'initial_balance' => '0.00', 'color' => '#000000']);
        $base    = ['account_id' => $account->id, 'start_date' => '2026-01-01'];

        $this->charge($base + ['name' => 'Colegiatura vencida', 'amount' => '1000.00', 'day_of_month' => 10, 'next_application_date' => '2026-11-10']);
        $this->charge($base + ['name' => 'Telmex por venir', 'amount' => '599.00', 'day_of_month' => 30, 'next_application_date' => '2026-11-30']);
        $done = $this->charge($base + ['name' => 'Gimnasio aplicado', 'amount' => '429.00', 'day_of_month' => 14, 'next_application_date' => '2026-12-14']);
        $this->charge($base + ['name' => 'Revista omitida', 'amount' => '50.00', 'day_of_month' => 5, 'next_application_date' => '2026-12-05']);

        \App\Models\Transaction::create(['date' => '2026-11-14', 'type' => 'expense', 'amount' => '429.00', 'account_id' => $account->id,
            'description' => 'PF SATELITE', 'recurring_charge_id' => $done->id]);

        $board = app(\App\Services\RecurringChargeService::class)->monthBoard();

        $this->assertSame(['Colegiatura vencida'], $board['overdue']->pluck('name')->all());
        $this->assertSame(['Telmex por venir'], $board['upcoming']->pluck('name')->all());
        $this->assertSame(['Gimnasio aplicado'], $board['done']->map(fn ($d) => $d['charge']->name)->all());
        $this->assertSame(['Revista omitida'], $board['skipped']->pluck('name')->all());
        $this->assertSame(['1599.00', '429.00', '2028.00', 21], [$board['pending_total'], $board['done_total'], $board['month_total'], $board['progress']]);

        $this->actingAsVerified(\App\Models\User::factory()->create())->get(route('recurring.index'))
            ->assertOk()
            ->assertSeeInOrder(['$1,599.00', 'por aplicar en 2 cargos', '1 ya venció', 'Vencidos', 'Colegiatura vencida', 'hace 6 días',
                'Lo que falta de noviembre', 'Telmex por venir', 'en 14 días', 'Ya aplicados en noviembre', 'Gimnasio aplicado', 'Aplicado el 14 nov',
                'Revista omitida', 'Sin movimiento este mes', 'Programados · 4'], false);

        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_skip_advances_next_date_without_creating_transaction(): void
    {
        $charge = $this->charge();

        $this->actingAsVerified(User::factory()->create())
            ->post(route('recurring.skip', $charge))
            ->assertRedirect(route('recurring.index'));

        $charge->refresh();

        $this->assertSame('2026-10-01', $charge->next_application_date->toDateString());
        $this->assertSame(0, $charge->applied_installments);
        $this->assertTrue($charge->is_active);
        $this->assertSame(0, Transaction::count());
    }

    public function test_skip_past_end_date_deactivates_charge(): void
    {
        $charge = $this->charge(['end_date' => '2026-09-15']);

        $this->actingAsVerified(User::factory()->create())
            ->post(route('recurring.skip', $charge));

        $charge->refresh();

        $this->assertFalse($charge->is_active);
        $this->assertSame('2026-10-01', $charge->next_application_date->toDateString());
    }

    public function test_index_uses_confirm_modal_instead_of_native_confirm(): void
    {
        $this->charge();

        $this->actingAsVerified(User::factory()->create())
            ->get(route('recurring.index'))
            ->assertOk()
            ->assertSee('data-confirm-label="Omitir"', false)
            ->assertSee('confirm-modal.window', false)
            ->assertDontSee('return confirm(', false);
    }

    public function test_skip_on_inactive_charge_does_nothing(): void
    {
        $charge = $this->charge(['is_active' => false]);

        $this->actingAsVerified(User::factory()->create())
            ->post(route('recurring.skip', $charge))
            ->assertRedirect(route('recurring.index'));

        $this->assertSame('2026-09-01', $charge->fresh()->next_application_date->toDateString());
    }
}
