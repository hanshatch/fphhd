<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Goal;
use App\Models\User;
use App\Services\GoalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\WithTotpSession;

class GoalTest extends TestCase
{
    use RefreshDatabase, WithTotpSession;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-03 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function goal(array $extra = []): Goal
    {
        $goal = Goal::create(array_merge([
            'name'          => 'Viaje a Japón',
            'kind'          => 'trip',
            'target_amount' => '12000.00',
            'target_date'   => '2027-01-20',
        ], $extra));

        if (isset($extra['created_at'])) {
            $goal->forceFill(['created_at' => $extra['created_at']])->save();
        }

        return $goal->fresh();
    }

    private function service(): GoalService
    {
        return app(GoalService::class);
    }

    // ── Cálculo de la cuota ───────────────────────────────────────────

    public function test_quota_spreads_remaining_until_the_month_before_the_goal(): void
    {
        // Oct, Nov, Dic → el dinero completo al inicio de enero
        $s = $this->service()->summary($this->goal());

        $this->assertSame(3, $s['months_left']);
        $this->assertSame('4000.00', $s['quota']);
        $this->assertSame('12000.00', $s['remaining']);
        $this->assertSame('on_track', $s['status']);
    }

    public function test_quota_rounds_up_to_the_cent_and_last_month_adjusts(): void
    {
        $goal = $this->goal(['target_amount' => '1000.00']);
        $s    = $this->service()->summary($goal);

        $this->assertSame('333.34', $s['quota']);

        $rows = $this->service()->schedule(collect([$goal->loadSum('contributions', 'amount')]));
        $this->assertSame('333.34', $rows[0]['contribute']);
        $this->assertSame('333.34', $rows[1]['contribute']);
        $this->assertSame('333.32', $rows[2]['contribute']);
        $this->assertSame('1000.00', $rows[3]['payout']);
    }

    public function test_contributions_reduce_the_quota(): void
    {
        $goal = $this->goal();
        $this->service()->contribute($goal, '3000.00', '2026-10-01');
        $this->service()->contribute($goal, '-600.00', '2026-10-02', 'Lo usé');

        $s = $this->service()->summary($goal->fresh());

        $this->assertSame('2400.00', $s['saved']);
        $this->assertSame('9600.00', $s['remaining']);
        $this->assertSame('3200.00', $s['quota']);
        $this->assertSame(20, $s['pct']);
    }

    public function test_schedule_shows_contribution_reserved_and_payout_per_month(): void
    {
        $goal = $this->goal()->loadSum('contributions', 'amount');
        $rows = $this->service()->schedule(collect([$goal]));

        $this->assertSame('2026-10', $rows[0]['month']->format('Y-m'));
        $this->assertSame(['4000.00', '4000.00'], [$rows[0]['contribute'], $rows[0]['reserved']]);
        $this->assertSame(['4000.00', '8000.00'], [$rows[1]['contribute'], $rows[1]['reserved']]);
        $this->assertSame('12000.00', $rows[2]['reserved']);
        $this->assertSame('12000.00', $rows[3]['payout']);
        $this->assertSame('0.00', $rows[3]['reserved']);
        $this->assertCount(1, $rows[3]['due']);
    }

    // ── Estados ───────────────────────────────────────────────────────

    public function test_goal_is_behind_when_saved_is_below_the_linear_plan(): void
    {
        // Creada en agosto para diciembre: a octubre debería llevar la mitad
        $goal = $this->goal(['target_amount' => '8000.00', 'target_date' => '2026-12-10', 'created_at' => '2026-08-05']);
        $this->service()->contribute($goal, '1000.00', '2026-09-01');

        $s = $this->service()->summary($goal->fresh());

        $this->assertSame('4000.00', $s['expected']);
        $this->assertSame('behind', $s['status']);
        $this->assertSame('3500.00', $s['quota']);
    }

    public function test_overdue_goal_asks_for_everything_now(): void
    {
        $goal = $this->goal(['target_amount' => '5000.00', 'target_date' => '2026-09-15', 'created_at' => '2026-06-01']);

        $s = $this->service()->summary($goal);

        $this->assertSame('overdue', $s['status']);
        $this->assertSame(1, $s['months_left']);
        $this->assertSame('5000.00', $s['quota']);
    }

    public function test_fully_saved_goal_needs_no_more_contributions(): void
    {
        $goal = $this->goal(['target_amount' => '2000.00']);
        $this->service()->contribute($goal, '2500.00', '2026-10-01');

        $s = $this->service()->summary($goal->fresh());

        $this->assertSame('funded', $s['status']);
        $this->assertSame('0.00', $s['quota']);
        $this->assertSame(100, $s['pct']);
    }

    // ── Pantallas ─────────────────────────────────────────────────────

    public function test_store_with_breakdown_sums_items_as_target(): void
    {
        $this->actingAsVerified(User::factory()->create());

        $this->post(route('goals.store'), [
            'name'        => 'Cancún',
            'kind'        => 'trip',
            'target_date' => '2027-03-15',
            'items'       => [
                ['description' => 'Vuelo', 'amount' => '8,500.50'],
                ['description' => 'Hotel', 'amount' => '12000'],
                ['description' => '', 'amount' => ''],
            ],
        ])->assertRedirect();

        $goal = Goal::with('items')->first();
        $this->assertSame('20500.50', (string) $goal->target_amount);
        $this->assertSame(['Vuelo', 'Hotel'], $goal->items->pluck('description')->all());
    }

    public function test_store_requires_total_or_items(): void
    {
        $this->actingAsVerified(User::factory()->create());

        $this->post(route('goals.store'), [
            'name' => 'Sin costo', 'kind' => 'project', 'target_date' => '2027-01-01',
        ])->assertSessionHasErrors('target_amount');
    }

    public function test_contribute_and_withdraw_from_screen(): void
    {
        $this->actingAsVerified(User::factory()->create());
        $goal = $this->goal();

        $this->post(route('goals.contribute', $goal), ['amount' => '1,500', 'direction' => 'add', 'date' => '2026-10-03'])
            ->assertRedirect(route('goals.show', $goal));
        $this->post(route('goals.contribute', $goal), ['amount' => '200', 'direction' => 'withdraw', 'date' => '2026-10-03']);

        $this->assertSame('1300.00', $this->service()->saved($goal->fresh()));
    }

    public function test_complete_removes_goal_from_the_monthly_plan(): void
    {
        $this->actingAsVerified(User::factory()->create());
        $goal = $this->goal();

        $this->post(route('goals.complete', $goal))->assertRedirect(route('goals.index'));

        $overview = $this->service()->overview();
        $this->assertTrue($overview['active']->isEmpty());
        $this->assertSame('0.00', $overview['totals']['month']);
        $this->assertCount(1, $overview['completed']);
    }

    public function test_committed_by_account_flags_when_saved_exceeds_balance(): void
    {
        $account = Account::create(['name' => 'Cajita', 'type' => 'savings', 'institution' => 'other', 'initial_balance' => '1000.00', 'color' => '#76a72b']);
        $goal    = $this->goal(['account_id' => $account->id]);
        $this->service()->contribute($goal, '1500.00', '2026-10-01');

        $row = $this->service()->overview()['byAccount']->first();

        $this->assertSame('1500.00', $row['committed']);
        $this->assertTrue($row['short']);
    }

    public function test_screens_render(): void
    {
        $this->actingAsVerified(User::factory()->create());

        $this->get(route('goals.index'))->assertOk()->assertSee('Planea tus próximos gastos grandes');

        $goal = $this->goal();
        $goal->items()->create(['description' => 'Vuelo', 'amount' => '12000.00']);
        $this->service()->contribute($goal, '1000.00', '2026-10-01');

        $this->get(route('goals.index'))->assertOk()->assertSee('Viaje a Japón')->assertSee('Plan mes a mes');
        $this->get(route('goals.show', $goal))->assertOk()->assertSee('Vuelo')->assertSee('Apartar dinero');
        $this->get(route('goals.create'))->assertOk();
        $this->get(route('goals.edit', $goal))->assertOk();
        $this->get(route('dashboard'))->assertOk()->assertSee('Metas · aparta este mes');
    }
}
