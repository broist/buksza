<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
    }

    private function login(): void
    {
        $this->actingAs(User::factory()->create());
    }

    private function data(array $extra = []): array
    {
        return ['client_id' => (string) Str::uuid(), 'date' => '2026-10-14', 'type' => 'expense', 'amount' => 2500, 'category_id' => 1, 'subcategory' => null, 'source' => null, 'note' => 'Bevásárlás', 'business' => false, ...$extra];
    }

    public function test_app_requires_login_and_only_one_user_can_register(): void
    {
        $this->get('/')->assertRedirect('/belepes');
        $this->post('/elso-belepes', ['name' => 'Isti', 'email' => 'isti@example.test', 'password' => 'test-password-2026', 'password_confirmation' => 'test-password-2026'])->assertRedirect('/');
        $this->assertDatabaseCount('users', 1);
        $this->post('/elso-belepes', ['name' => 'Anna', 'email' => 'anna@example.test', 'password' => 'test-password-2026', 'password_confirmation' => 'test-password-2026'])->assertForbidden();
    }

    public function test_seed_has_23_categories_no_fake_financial_data_and_inactive_optional_categories(): void
    {
        $this->assertDatabaseCount('categories', 23);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('users', 0);
        self::assertFalse(Category::find(22)->active);
        self::assertFalse(Category::find(23)->active);
    }

    public function test_create_idempotency_edit_delete_and_restore_recompute_totals(): void
    {
        $this->login();
        $data = $this->data();
        $this->post('/tetelek', $data)->assertSessionHasNoErrors();
        $this->post('/tetelek', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('transactions', 1);
        $tx = Transaction::first();
        $this->put('/tetelek/'.$tx->id, [...$data, 'amount' => 3200])->assertSessionHasNoErrors();
        $this->get('/?year=2026&month=10')->assertInertia(fn (Assert $p) => $p->where('report.expense', 3200));
        $this->delete('/tetelek/'.$tx->id)->assertRedirect();
        $this->get('/?year=2026&month=10')->assertInertia(fn (Assert $p) => $p->where('report.expense', 0));
        $this->post('/tetelek/'.$tx->id.'/visszavonas')->assertRedirect();
        $this->get('/?year=2026&month=10')->assertInertia(fn (Assert $p) => $p->where('report.expense', 3200));
    }

    public function test_invalid_amount_category_subcategory_and_date_are_rejected(): void
    {
        $this->login();
        foreach ([['amount' => 0], ['amount' => 1.5], ['category_id' => 999], ['category_id' => 22], ['date' => '2026-02-30'], ['subcategory' => 'Nem létező']] as $bad) {
            $this->post('/tetelek', $this->data($bad))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_category_can_be_created_renamed_archived_and_reordered_without_losing_history(): void
    {
        $this->login();
        $this->post('/tetelek', $this->data());
        $this->put('/kategoriak/1', ['name' => 'Bevásárlás', 'color' => '#123456', 'active' => false, 'carryover' => false, 'subcategories' => ['bolt']])->assertSessionHasNoErrors();
        $this->get('/?year=2026&month=10')->assertInertia(fn (Assert $p) => $p->where('report.expense', 2500)->where('categories.0.name', 'Bevásárlás'));
        $this->post('/kategoriak', ['name' => 'Új kategória', 'color' => '#abcdef', 'active' => true, 'carryover' => true, 'subcategories' => []])->assertSessionHasNoErrors();
        $ids = Category::orderByDesc('position')->pluck('id')->all();
        $this->post('/kategoriak/sorrend', ['ids' => $ids])->assertRedirect();
        self::assertSame($ids[0], Category::orderBy('position')->first()->id);
    }

    public function test_monthly_plan_override_template_future_and_copy_preserve_history(): void
    {
        $this->login();
        $this->travelTo(now()->setDate(2026, 10, 14));
        $this->get('/?year=2026&month=10');
        $this->post('/tervek', ['year' => 2026, 'month' => 11, 'mode' => 'monthly', 'amounts' => [1 => 9000]])->assertSessionHasNoErrors();
        $this->post('/tervek', ['year' => 2026, 'month' => 10, 'mode' => 'base', 'amounts' => [1 => 5000]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('monthly_plans', ['year' => 2026, 'month' => 10, 'category_id' => 1, 'amount' => 0]);
        $this->assertDatabaseHas('monthly_plans', ['year' => 2026, 'month' => 11, 'category_id' => 1, 'amount' => 9000]);
        $this->assertDatabaseHas('monthly_plans', ['year' => 2026, 'month' => 12, 'category_id' => 1, 'amount' => 5000]);
        $this->post('/tervek', ['year' => 2026, 'month' => 12, 'mode' => 'copy', 'amounts' => [1 => 0]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('monthly_plans', ['year' => 2026, 'month' => 12, 'category_id' => 1, 'amount' => 9000]);
    }

    public function test_beginning_amounts_and_daily_note_persist_and_do_not_create_transactions(): void
    {
        $this->login();
        $this->post('/beallitasok', ['year' => 2026, 'opening_amount' => 10000, 'savings_rate' => '12.5', 'opening_balance' => 30000, 'opening_date' => '2026-01-01'])->assertSessionHasNoErrors();
        $this->post('/napi-megjegyzes', ['date' => '2026-10-14', 'note' => 'Családi nap'])->assertSessionHasNoErrors();
        $this->get('/?year=2026&month=10')->assertInertia(fn (Assert $p) => $p->where('settings.savings_rate', '12.5')->where('account.balance', 30000)->where('report.closing.text', '10 000')->where('notes.2026-10-14', 'Családi nap'));
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_financial_writes_are_not_available_without_authentication(): void
    {
        $this->post('/tetelek', $this->data())->assertRedirect('/belepes');
        $this->post('/beallitasok', [])->assertRedirect('/belepes');
        $this->post('/kategoriak', [])->assertRedirect('/belepes');
    }

    public function test_an_earlier_month_does_not_invent_a_balance_before_the_opening_date(): void
    {
        $this->login();
        DB::table('accounts')->where('id', 1)->update(['opening_date' => '2026-10-01', 'opening_balance' => 500000]);
        $this->get('/?year=2026&month=9')->assertInertia(fn (Assert $p) => $p->where('account.balance',null));
        $this->get('/?year=2026&month=10')->assertInertia(fn (Assert $p) => $p->where('account.balance',500000));
    }
}
