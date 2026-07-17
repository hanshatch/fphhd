<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\RecurringCharge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTotpSession;

class RecurringChargeUpdateTest extends TestCase
{
    use RefreshDatabase, WithTotpSession;

    private function charge(array $extra = []): RecurringCharge
    {
        $account = Account::create([
            'name'            => 'Débito',
            'type'            => 'debit',
            'institution'     => 'banamex',
            'initial_balance' => '0.00',
            'color'           => '#76a72b',
        ]);

        return RecurringCharge::create(array_merge([
            'name'                  => 'Netflix',
            'account_id'            => $account->id,
            'type'                  => 'expense',
            'amount'                => '199.00',
            'day_of_month'          => 5,
            'start_date'            => now()->subMonths(2)->toDateString(),
            'next_application_date' => now()->addMonth()->startOfMonth()->setDay(5)->toDateString(),
            'is_active'             => true,
        ], $extra));
    }

    private function updatePayload(RecurringCharge $charge, array $overrides = []): array
    {
        return array_merge([
            'name'         => $charge->name,
            'account_id'   => $charge->account_id,
            'type'         => $charge->type,
            'amount'       => (string) $charge->amount,
            'day_of_month' => $charge->day_of_month,
            'start_date'   => $charge->start_date->toDateString(),
        ], $overrides);
    }

    public function test_changing_day_of_month_recalculates_next_application_date(): void
    {
        $charge = $this->charge();

        $newDay = now()->day > 25 ? 1 : now()->day + 2; // día futuro cercano

        $this->actingAsVerified(User::factory()->create())
            ->put(route('recurring.update', $charge), $this->updatePayload($charge, ['day_of_month' => $newDay]))
            ->assertRedirect(route('recurring.index'));

        $charge->refresh();

        $this->assertSame($newDay, $charge->day_of_month);
        $this->assertSame($newDay, $charge->next_application_date->day);
        $this->assertTrue($charge->next_application_date->gte(now()->startOfDay()));
    }

    public function test_next_date_never_lands_in_the_past(): void
    {
        $charge = $this->charge();

        $pastDay = max(1, now()->day - 1); // ese día ya pasó este mes

        $this->actingAsVerified(User::factory()->create())
            ->put(route('recurring.update', $charge), $this->updatePayload($charge, ['day_of_month' => $pastDay]));

        $charge->refresh();

        $this->assertTrue($charge->next_application_date->gte(now()->startOfDay()));
        $this->assertSame($pastDay, $charge->next_application_date->day);
    }

    public function test_update_without_date_changes_keeps_next_application_date(): void
    {
        $charge   = $this->charge();
        $original = $charge->next_application_date->toDateString();

        $this->actingAsVerified(User::factory()->create())
            ->put(route('recurring.update', $charge), $this->updatePayload($charge, ['amount' => '249.00']));

        $charge->refresh();

        $this->assertSame('249.00', (string) $charge->amount);
        $this->assertSame($original, $charge->next_application_date->toDateString());
    }
}
