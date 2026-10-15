<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountsLoansTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
        $this->actingAs(User::factory()->create());
        $this->travelTo(now()->setDate(2026, 10, 14));
    }

    private function tx(array $extra = []): array
    {
        return ['client_id' => (string) Str::uuid(), 'date' => '2026-10-14', 'type' => 'expense', 'amount' => 1000, 'category_id' => 1, 'subcategory' => null, 'source' => null, 'note' => '', 'business' => false, 'account_id' => 1, 'target_account_id' => null, 'loan_id' => null, 'fee_amount' => 0, 'person' => '', ...$extra];
    }

    private function loan(array $extra = []): Loan
    {
        $this->post('/hitelek', ['name' => 'Hitel', 'lender' => 'Bank', 'original_amount' => 1000000, 'outstanding_amount' => 800000, 'balance_date' => '2026-10-01', 'annual_rate' => '7.5', 'rate_terms' => 'Fix', 'installment' => 50000, 'due_day' => 31, 'start_date' => '2026-01-01', 'effective_date' => '2026-01-01', 'end_date' => null, 'closed_date' => null, 'note' => '', ...$extra])->assertSessionHasNoErrors();

        return Loan::latest('id')->first();
    }

    public function test_raiffeisen_transfer_has_two_balances_one_fee_and_only_purchase_is_expense(): void
    {
        $data = $this->tx(['type' => 'transfer', 'amount' => 170000, 'target_account_id' => 3, 'fee_amount' => 700]);
        $this->post('/tetelek', $data)->assertSessionHasNoErrors();
        $this->post('/tetelek', $data)->assertSessionHasNoErrors();
        $this->post('/tetelek', $this->tx(['account_id' => 3, 'amount' => 12345]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('transactions', 2);
        $this->get('/?year=2026&month=10')->assertInertia(fn (Assert $p) => $p->where('report.expense', 13045)->where('report.cf.text', '−13 045')->where('accounts.0.balance', -170700)->where('accounts.2.balance', 157655)->where('annual.expense', 13045));
        $this->get('/honap?year=2026&month=10&account_id=1')->assertInertia(fn (Assert $p) => $p->where('scopedReport.expense', 700)->where('report.expense', 13045)->where('report.categories.0.remaining', -12345));
        $this->get('/honap?year=2026&month=10&account_id=3')->assertInertia(fn (Assert $p) => $p->where('scopedReport.expense', 12345));
        $tx = Transaction::where('type', 'transfer')->first();
        $this->delete('/tetelek/'.$tx->id);
        $this->get('/?year=2026&month=10')->assertInertia(fn (Assert $p) => $p->where('report.expense', 12345)->where('accounts.0.balance', 0));
        $this->post('/tetelek/'.$tx->id.'/visszavonas');
        $this->get('/?year=2026&month=10')->assertInertia(fn (Assert $p) => $p->where('accounts.0.balance', -170700));
    }

    public function test_reserve_return_and_three_separate_loans_count_actual_payments_once(): void
    {
        $a = $this->loan();
        $b = $this->loan(['name' => 'Második', 'annual_rate' => '3.25', 'installment' => 20000]);
        $c = $this->loan(['name' => 'Áruhitel', 'start_date' => '2026-10-01', 'effective_date' => '2026-10-01', 'annual_rate' => '0', 'installment' => 10000]);
        $this->post('/tetelek', $this->tx(['type' => 'transfer', 'target_account_id' => 5, 'amount' => 90000, 'loan_id' => $a->id, 'note' => 'hitel + többlet']))->assertSessionHasNoErrors();
        $this->post('/tetelek', $this->tx(['type' => 'transfer', 'account_id' => 5, 'target_account_id' => 1, 'amount' => 50000, 'loan_id' => $a->id]))->assertSessionHasNoErrors();
        foreach ([[$a, 50000], [$b, 20000], [$c, 10000]] as [$loan, $amount]) {
            $this->post('/tetelek', $this->tx(['amount' => $amount, 'category_id' => 13, 'loan_id' => $loan->id]))->assertSessionHasNoErrors();
        }
        $this->get('/hitelek?year=2026&month=10')->assertInertia(fn (Assert $p) => $p->where('report.expense', 80000)->where('annual.expense', 80000)->where('loanTotals.paid', 80000)->where('loanTotals.remaining', 0)->where('loans.0.paid', 50000)->has('loans.0.reserves', 2)->where('accounts.4.balance', 40000)->where('loans.0.outstanding_amount', 800000));
        $this->get('/hitelek?year=2026&month=9')->assertInertia(fn (Assert $p) => $p->where('loans.2.expected', 0));
    }

    public function test_szep_benefit_and_health_funding_keep_restricted_balances_separate(): void
    {
        $this->post('/tetelek', $this->tx(['type' => 'income', 'account_id' => 6, 'source' => 'Egyéb', 'amount' => 30000]))->assertSessionHasNoErrors();
        $this->post('/tetelek', $this->tx(['type' => 'transfer', 'target_account_id' => 7, 'amount' => 10000]))->assertSessionHasNoErrors();
        $this->post('/tetelek', $this->tx(['account_id' => 7, 'category_id' => 8, 'amount' => 3000]))->assertSessionHasNoErrors();
        $this->get('/?year=2026&month=10')->assertInertia(fn (Assert $p) => $p->where('report.income', 30000)->where('report.expense', 3000)->where('report.incomeSources.Munka', 30000)->where('report.cf.text', '24 000')->where('accounts.0.balance', -10000)->where('accounts.5.balance', 30000)->where('accounts.6.balance', 7000));
    }

    public function test_anna_snapshots_do_not_create_or_infer_any_cash_flow(): void
    {
        $this->post('/tetelek', $this->tx(['type' => 'income', 'amount' => 120000, 'source' => 'Egyéb', 'person' => 'Anna']))->assertSessionHasNoErrors();
        foreach ([56000, 54000] as $amount) {
            $this->post('/szamlak/2/helyzetjelentes', ['date' => '2026-08-31', 'amount' => $amount, 'note' => 'Hó vége'])->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('account_snapshots', 1);
        $this->assertDatabaseCount('transactions', 1);
        $this->get('/?year=2026&month=10&person=Anna')->assertInertia(fn (Assert $p) => $p->where('scopedReport.income', 120000)->where('report.cf.text', '108 000')->where('accounts.1.balance', null)->where('accounts.1.latest_snapshot.amount', 54000)->where('accounts.1.month_reported', false)->where('accounts.0.balance', 120000));
        $this->get('/?year=2026&month=8')->assertInertia(fn (Assert $p) => $p->where('accounts.1.month_reported', true));
        $this->post('/szamlak/2/helyzetjelentes', ['date' => '2026-08-30', 'amount' => 1])->assertSessionHasErrors('date');
        $this->post('/szamlak/2/helyzetjelentes', ['date' => '2026-10-31', 'amount' => 1])->assertSessionHasErrors('date');
    }

    public function test_pocket_and_aggregate_modes_count_funding_once_and_preserve_old_purchases_on_mode_change(): void
    {
        $this->post('/tetelek', $this->tx(['type' => 'transfer', 'target_account_id' => 4, 'amount' => 40000]))->assertSessionHasNoErrors();
        $this->post('/tetelek', $this->tx(['account_id' => 4, 'category_id' => null, 'amount' => 9000]))->assertSessionHasNoErrors();
        $this->get('/honap?year=2026&month=10&account_id=4')->assertInertia(fn (Assert $p) => $p->where('scopedReport.expense', 40000)->where('report.expense', 40000)->where('accounts.3.balance', 31000));
        $old = $this->tx(['account_id' => 3, 'date' => '2026-10-13', 'amount' => 500]);
        $this->post('/tetelek', $old)->assertSessionHasNoErrors();
        $account = Account::find(3)->toArray();
        $this->put('/szamlak/3', [...$account, 'mode' => 'aggregate', 'funding_category_id' => 1, 'effective_date' => '2026-10-14'])->assertSessionHasNoErrors();
        $this->post('/tetelek', $this->tx(['type' => 'transfer', 'target_account_id' => 3, 'amount' => 2000]))->assertSessionHasNoErrors();
        $this->post('/tetelek', $this->tx(['account_id' => 3, 'amount' => 300]))->assertSessionHasNoErrors();
        $oldTx = Transaction::where('client_id', $old['client_id'])->first();
        $this->put('/tetelek/'.$oldTx->id, [...$old, 'amount' => 600])->assertSessionHasNoErrors();
        $this->get('/honap?year=2026&month=10')->assertInertia(fn (Assert $p) => $p->where('report.expense', 42600)->where('report.categories.0.spent', 2600)->where('accounts.2.balance', 1100));
        $this->put('/szamlak/3', [...$account, 'mode' => 'pocket', 'effective_date' => '2026-10-13'])->assertSessionHasErrors('effective_date');
    }

    public function test_child_and_family_savings_have_real_target_balances_without_double_cf_deduction(): void
    {
        $this->post('/szamlak', ['name' => 'Megtakarítás', 'owner' => 'közös', 'kind' => 'saving', 'mode' => 'detailed', 'bank' => '', 'role' => '', 'active' => true, 'parent_id' => null, 'funding_category_id' => null, 'opening_balance' => 0, 'opening_date' => '2026-01-01', 'effective_date' => '2026-01-01'])->assertSessionHasNoErrors();
        $saving = Account::latest('id')->first();
        $this->post('/tetelek', $this->tx(['type' => 'income', 'source' => 'Munka', 'amount' => 100000]))->assertSessionHasNoErrors();
        $this->post('/tetelek', $this->tx(['type' => 'saving', 'target_account_id' => $saving->id, 'amount' => 20000]))->assertSessionHasNoErrors();
        $this->post('/tetelek', $this->tx(['type' => 'saving_return', 'target_account_id' => $saving->id, 'amount' => 5000]))->assertSessionHasNoErrors();
        $this->post('/tetelek', $this->tx(['type' => 'child_saving', 'target_account_id' => 8, 'amount' => 7000]))->assertSessionHasNoErrors();
        $this->get('/?year=2026&month=10')->assertInertia(fn (Assert $p) => $p->where('report.actualSavings', 15000)->where('report.childSavings', 7000)->where('report.savingsTarget.text', '10 000')->where('report.cf.text', '83 000')->where('accounts.0.balance', 78000)->where('accounts.7.balance', 7000)->where('accounts.8.balance', 15000));
    }

    public function test_loan_versions_preserve_old_rate_and_short_month_due_day_and_closure_history(): void
    {
        $loan = $this->loan();
        $this->put('/hitelek/'.$loan->id, [...$loan->toArray(), 'installment' => 55000, 'annual_rate' => '8.25', 'effective_date' => '2026-11-01', 'closed_date' => '2026-11-30'])->assertSessionHasNoErrors();
        $this->get('/hitelek?year=2026&month=2')->assertInertia(fn (Assert $p) => $p->where('loans.0.expected', 50000)->where('loans.0.due_date', '2026-02-28')->where('loans.0.terms.annual_rate', '7.5')->has('loans.0.history', 2));
        $this->get('/hitelek?year=2026&month=11')->assertInertia(fn (Assert $p) => $p->where('loans.0.expected', 55000)->where('loans.0.due_date', '2026-11-30')->where('loans.0.terms.annual_rate', '8.25'));
        $this->get('/hitelek?year=2026&month=12')->assertInertia(fn (Assert $p) => $p->where('loans.0.expected', 0));
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_browser_numeric_strings_are_returned_as_numbers_in_loan_history(): void
    {
        $this->loan(['installment' => '50000', 'due_day' => '25', 'original_amount' => '1000000', 'outstanding_amount' => '800000']);
        $this->get('/hitelek?year=2026&month=10')->assertInertia(fn (Assert $p) => $p->where('loans.0.history.0.data.installment', 50000)->where('loans.0.history.0.data.original_amount', 1000000)->where('loans.0.history.0.data.due_day', 25));
    }

    public function test_due_day_change_uses_a_month_boundary_and_preserves_previous_month(): void
    {
        $loan = $this->loan(['due_day' => 10]);
        $values = [...$loan->toArray(), 'due_day' => 25, 'installment' => 55000, 'effective_date' => '2026-10-14'];
        $this->put('/hitelek/'.$loan->id, $values)->assertSessionHasErrors('effective_date');
        $this->put('/hitelek/'.$loan->id, [...$values, 'effective_date' => '2026-11-01'])->assertSessionHasNoErrors();
        $this->get('/hitelek?year=2026&month=10')->assertInertia(fn (Assert $p) => $p->where('loans.0.due_date', '2026-10-10')->where('loans.0.expected', 50000));
        $this->get('/hitelek?year=2026&month=11')->assertInertia(fn (Assert $p) => $p->where('loans.0.due_date', '2026-11-25')->where('loans.0.expected', 55000));
    }

    public function test_person_filtered_fee_keeps_its_original_transfer_available_for_editing(): void
    {
        Account::find(4)->update(['owner' => 'Anna']);
        $this->post('/tetelek', $this->tx(['type' => 'transfer', 'target_account_id' => 4, 'amount' => 1000, 'fee_amount' => 10]))->assertSessionHasNoErrors();
        $this->get('/honap?year=2026&month=10&person=Isti')->assertInertia(fn (Assert $p) => $p->where('scopedReport.expense', 10)->has('transactions', 1)->has('familyTransactions', 1)->where('familyTransactions.0.is_fee', true));
        $this->get('/honap?year=2026&month=10&person=Anna')->assertInertia(fn (Assert $p) => $p->where('scopedReport.expense', 1000)->where('report.expense', 1010));
    }

    public function test_invalid_transfer_snapshot_and_loan_links_are_rejected_and_archives_preserve_data(): void
    {
        $loan = $this->loan();
        foreach ([['type' => 'transfer', 'target_account_id' => 1], ['type' => 'transfer'], ['account_id' => 2], ['type' => 'transfer', 'target_account_id' => 2], ['loan_id' => $loan->id], ['type' => 'transfer', 'target_account_id' => 3, 'loan_id' => $loan->id], ['type' => 'child_saving', 'target_account_id' => 3]] as $bad) {
            $this->post('/tetelek', $this->tx($bad))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('transactions', 0);
        $this->post('/tetelek', $this->tx(['account_id' => 3]))->assertSessionHasNoErrors();
        $a = Account::find(3)->toArray();
        $this->put('/szamlak/3', [...$a, 'active' => false, 'effective_date' => '2026-10-14'])->assertSessionHasNoErrors();
        $this->post('/tetelek', $this->tx(['account_id' => 3]))->assertSessionHasErrors('account_id');
        $this->get('/?year=2026&month=10')->assertInertia(fn (Assert $p) => $p->where('report.expense', 1000));
        $this->actingAs(User::factory()->make());
        $this->app['auth']->forgetGuards();
        $this->post('/hitelek', [])->assertRedirect('/belepes');
    }
}
