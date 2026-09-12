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

    public function test_skip_on_inactive_charge_does_nothing(): void
    {
        $charge = $this->charge(['is_active' => false]);

        $this->actingAsVerified(User::factory()->create())
            ->post(route('recurring.skip', $charge))
            ->assertRedirect(route('recurring.index'));

        $this->assertSame('2026-09-01', $charge->fresh()->next_application_date->toDateString());
    }
}
