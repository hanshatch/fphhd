<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\IncomePlan;
use App\Models\RecurringCharge;
use App\Models\Source;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTotpSession;

/**
 * Todas las pantallas renderizan con datos reales (atrapa errores de Blade
 * tras cambios de interfaz) y el filtro de Reportes → Movimientos funciona.
 */
class PageSmokeTest extends TestCase
{
    use RefreshDatabase, WithTotpSession;

    private Account $account;
    private Category $category;
    private Source $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsVerified(User::factory()->create());

        $this->account  = Account::create(['name' => 'Cheques', 'type' => 'debit', 'institution' => 'other', 'initial_balance' => '1000.00', 'color' => '#76a72b']);
        $card           = Account::create(['name' => 'Tarjeta', 'type' => 'credit', 'institution' => 'other', 'initial_balance' => '0.00', 'color' => '#ef4444']);
        $this->category = Category::create(['name' => 'Comida', 'kind' => 'expense', 'color' => '#f97316', 'icon' => 'utensils']);
        $this->source   = Source::create(['name' => 'Agencia', 'kind' => 'agency']);

        Transaction::create(['date' => now()->toDateString(), 'type' => 'expense', 'amount' => '250.00', 'account_id' => $this->account->id, 'category_id' => $this->category->id, 'description' => 'Tacos']);
        Transaction::create(['date' => now()->toDateString(), 'type' => 'expense', 'amount' => '80.00', 'account_id' => $this->account->id, 'description' => 'Sin categoría']);
        Transaction::create(['date' => now()->toDateString(), 'type' => 'income', 'amount' => '5000.00', 'account_id' => $this->account->id, 'source_id' => $this->source->id, 'description' => 'Pago']);
        Transaction::create(['date' => now()->toDateString(), 'type' => 'transfer', 'amount' => '300.00', 'account_id' => $this->account->id, 'counterparty_account_id' => $card->id]);

        RecurringCharge::create([
            'name' => 'Netflix', 'account_id' => $card->id, 'category_id' => $this->category->id, 'type' => 'expense',
            'amount' => '199.00', 'day_of_month' => now()->day, 'start_date' => now()->toDateString(),
            'next_application_date' => now()->toDateString(), 'is_active' => true,
        ]);
        IncomePlan::create([
            'name' => 'Quincena', 'account_id' => $this->account->id, 'source_id' => $this->source->id,
            'expected_amount' => '10000.00', 'frequency' => 'monthly', 'day_1' => 15,
            'next_expected_date' => now()->addDays(5)->toDateString(), 'is_active' => true,
        ]);
    }

    public function test_every_screen_renders(): void
    {
        $urls = [
            route('dashboard'),
            route('transactions.index'),
            route('transactions.create'),
            route('accounts.index'),
            route('accounts.show', $this->account),
            route('accounts.adjust.show', $this->account),
            route('reports.index', ['type' => 'annual']),
            route('reports.index', ['type' => 'categories']),
            route('reports.index', ['type' => 'sources']),
            route('reports.index', ['type' => 'yields']),
            route('scheduled.index'),
            route('recurring.index'),
            route('income-plans.index'),
            route('settings'),
            route('more'),
            route('categories.index'),
            route('categories.create'),
            route('sources.index'),
            route('sources.create'),
            route('profile.edit'),
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_report_rows_drill_down_to_filtered_transactions(): void
    {
        $this->get(route('reports.index', ['type' => 'categories']))
            ->assertSee('category_id=' . $this->category->id, false)
            ->assertSee('category_id=none', false);

        $this->get(route('transactions.index', ['category_id' => $this->category->id]))
            ->assertOk()->assertSee('Tacos')->assertDontSee('Pago')->assertSee('Categoría: Comida');

        $this->get(route('transactions.index', ['category_id' => 'none', 'type' => 'expense']))
            ->assertOk()->assertSee('Sin categoría')->assertDontSee('Tacos');

        $this->get(route('transactions.index', ['source_id' => $this->source->id]))
            ->assertOk()->assertSee('Pago')->assertDontSee('Tacos')->assertSee('Fuente: Agencia');
    }
}
