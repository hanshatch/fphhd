<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\WithTotpSession;

class StatementImportTest extends TestCase
{
    use RefreshDatabase, WithTotpSession;

    private function account(): Account
    {
        return Account::create([
            'name'            => 'Amex',
            'type'            => 'credit',
            'institution'     => 'other',
            'initial_balance' => '0.00',
            'color'           => '#373737',
        ]);
    }

    private function fakeVision(array $charges): void
    {
        config(['services.openai.api_key' => 'test-oa-key']);

        Http::preventStrayRequests();
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['charges' => $charges])]]],
            ]),
        ]);
    }

    private function upload(Account $account, int $files = 1)
    {
        $images = [];
        for ($i = 0; $i < $files; $i++) {
            $images[] = UploadedFile::fake()->image("edo{$i}.png", 800, 1200);
        }

        return $this->actingAsVerified(User::factory()->create())
            ->post(route('accounts.import.upload', $account), ['images' => $images]);
    }

    public function test_upload_analyzes_and_shows_review_with_category_hint(): void
    {
        $account = $this->account();
        $super   = Category::create(['name' => 'Súper', 'kind' => 'expense']);

        $this->fakeVision([
            ['amount' => '4530.68', 'description' => 'Vallarta Satélite', 'date' => '2026-08-28', 'type' => 'expense', 'category' => 'Súper'],
            ['amount' => '179.00',  'description' => 'Apple.com/bill',    'date' => '2026-08-24', 'type' => 'expense', 'category' => null],
        ]);

        $response = $this->upload($account);
        $response->assertRedirect();

        $review = $this->get($response->headers->get('Location'));
        $review->assertOk()
            ->assertSee('Vallarta Satélite')
            ->assertSee('Apple.com/bill')
            ->assertSee('value="' . $super->id . '" selected', false);

        $this->assertSame(0, Transaction::count());
    }

    public function test_store_creates_only_selected_rows_with_chosen_category(): void
    {
        $account = $this->account();
        $super   = Category::create(['name' => 'Súper', 'kind' => 'expense']);

        $this->fakeVision([
            ['amount' => '4530.68', 'description' => 'Vallarta Satélite', 'date' => '2026-08-28', 'type' => 'expense', 'category' => null],
            ['amount' => '179.00',  'description' => 'Apple.com/bill',    'date' => '2026-08-24', 'type' => 'expense', 'category' => null],
        ]);

        $location = $this->upload($account)->headers->get('Location');
        $token    = basename($location);

        $this->post(route('accounts.import.store', [$account, $token]), ['rows' => [
            ['include' => 1, 'date' => '2026-08-28', 'description' => 'Vallarta Satélite', 'amount' => '4530.68', 'type' => 'expense', 'category_id' => $super->id],
            ['date' => '2026-08-24', 'description' => 'Apple.com/bill', 'amount' => '179.00', 'type' => 'expense', 'category_id' => ''],
        ]])->assertRedirect(route('accounts.show', $account));

        $this->assertSame(1, Transaction::count());

        $tx = Transaction::sole();
        $this->assertSame('4530.68', $tx->amount);
        $this->assertSame($account->id, $tx->account_id);
        $this->assertSame($super->id, $tx->category_id);
        $this->assertSame('expense', $tx->type);
        $this->assertSame('2026-08-28', $tx->date->toDateString());

        // El borrador se consume: reenviar ya no crea nada
        $this->post(route('accounts.import.store', [$account, $token]), ['rows' => [
            ['include' => 1, 'date' => '2026-08-24', 'description' => 'Apple.com/bill', 'amount' => '179.00', 'type' => 'expense'],
        ]])->assertRedirect(route('accounts.show', $account));

        $this->assertSame(1, Transaction::count());
    }

    public function test_existing_transaction_is_flagged_as_duplicate_and_unchecked(): void
    {
        $account = $this->account();

        Transaction::create([
            'date' => '2026-08-27', 'type' => 'expense', 'amount' => '179.00',
            'account_id' => $account->id, 'description' => 'Apple ya registrado',
        ]);

        $this->fakeVision([
            ['amount' => '179.00', 'description' => 'Apple.com/bill', 'date' => '2026-08-24', 'type' => 'expense', 'category' => null],
        ]);

        $location = $this->upload($account)->headers->get('Location');

        $this->get($location)
            ->assertOk()
            ->assertSee('Apple ya registrado')
            ->assertSee('parecen ya registrados');
    }

    public function test_overlapping_screenshots_are_deduplicated(): void
    {
        $account = $this->account();

        $this->fakeVision([
            ['amount' => '115.00', 'description' => 'Cinepolis', 'date' => '2026-08-15', 'type' => 'expense', 'category' => null],
        ]);

        $location = $this->upload($account, 2)->headers->get('Location');

        $this->get($location)->assertOk()->assertSee('Detecté <strong class="text-[#373737] dark:text-white">1</strong>', false);
    }

    public function test_upload_without_vision_key_redirects_with_message(): void
    {
        config(['services.openai.api_key' => null]);
        $account = $this->account();

        $this->actingAsVerified(User::factory()->create())
            ->post(route('accounts.import.upload', $account), ['images' => [UploadedFile::fake()->image('x.png')]])
            ->assertRedirect(route('accounts.show', $account))
            ->assertSessionHas('status');
    }
}
