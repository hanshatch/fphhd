<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTotpSession;

class PagesSmokeTest extends TestCase
{
    use RefreshDatabase, WithTotpSession;

    public function test_all_main_pages_render(): void
    {
        $user = User::factory()->create();

        $debit = Account::create([
            'name' => 'Banamex', 'type' => 'debit', 'institution' => 'banamex',
            'initial_balance' => '1000.00', 'color' => '#76a72b',
        ]);
        $tdc = Account::create([
            'name' => 'TDC', 'type' => 'credit', 'institution' => 'other',
            'initial_balance' => '0.00', 'color' => '#373737',
        ]);
        $tdc->creditCard()->create([
            'statement_day' => 5, 'payment_day' => 20,
            'credit_limit' => '50000.00', 'min_payment_pct' => 1.5,
        ]);

        $cat = Category::create(['name' => 'Comida', 'kind' => 'expense', 'color' => '#ef4444']);

        Transaction::create([
            'date' => now()->toDateString(), 'type' => 'expense', 'amount' => '150.00',
            'account_id' => $debit->id, 'category_id' => $cat->id,
        ]);
        Transaction::create([
            'date' => now()->toDateString(), 'type' => 'interest', 'amount' => '45.00',
            'account_id' => $debit->id,
        ]);
        Transaction::create([
            'date' => now()->toDateString(), 'type' => 'transfer', 'amount' => '500.00',
            'account_id' => $debit->id, 'counterparty_account_id' => $tdc->id,
        ]);

        Budget::create(['category_id' => $cat->id, 'amount' => '2000.00']);

        $routes = [
            '/dashboard',
            '/accounts',
            route('accounts.show', $debit),
            route('accounts.show', $tdc),
            route('accounts.adjust.show', $debit),
            '/transactions',
            '/transactions/create',
            '/budgets',
            '/scheduled',
            '/reports?type=annual',
            '/reports?type=categories',
            '/reports?type=sources',
            '/recurring',
            '/income-plans',
            '/categories',
            '/sources',
        ];

        foreach ($routes as $route) {
            $this->actingAsVerified($user)->get($route)->assertOk();
        }
    }
}
