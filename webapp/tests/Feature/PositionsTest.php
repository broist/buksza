<?php

namespace Tests\Feature;

use App\Finance\AnnualExport;
use App\Finance\FinanceBackup;
use App\Finance\Positions;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PositionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
        $this->actingAs(User::factory()->create());
        $this->travelTo(now()->setDate(2026, 10, 15));
    }

    private function position(string $kind = 'receivable', int $opening = 0): int
    {
        $this->post('/nyilvantartasok', ['kind' => $kind, 'name' => 'Teszt cél / partner', 'start_date' => '2026-01-01', 'opening_amount' => $opening, 'target_amount' => 200000, 'monthly_income' => 700000])->assertSessionHasNoErrors();

        return DB::table('finance_positions')->max('id');
    }

    private function payment(int $id, string $type, int $amount, array $extra = []): Transaction
    {
        $data = ['client_id' => (string) Str::uuid(), 'date' => '2026-10-01', 'type' => $type, 'amount' => $amount, 'account_id' => 1, 'business' => false, ...$extra];
        $this->post("/nyilvantartasok/$id/mozgas", $data)->assertSessionHasNoErrors();

        return Transaction::where('client_id', $data['client_id'])->firstOrFail();
    }

    public function test_interest_free_loans_have_four_separate_cash_flows_without_income_expense_or_savings_inflation(): void
    {
        $a = $this->position();
        $b = $this->position('payable');
        $this->payment($a, 'loan_given', 100000);
        $this->payment($a, 'loan_given_return', 25000);
        $this->payment($b, 'loan_received', 80000);
        $this->payment($b, 'loan_repaid', 10000);
        $this->get('/kintlevosegeim?year=2026&month=10')->assertOk()->assertInertia(fn ($p) => $p
            ->where('report.income', 0)->where('report.expense', 0)->where('report.savingsTarget.numerator', '0')->where('report.cf.numerator', '-5000')
            ->where('report.loanFlows.loan_given', 100000)->where('report.loanFlows.loan_given_return', 25000)->where('report.loanFlows.loan_received', 80000)->where('report.loanFlows.loan_repaid', 10000)
            ->where('account.balance', -5000)->where('positions.items.0.balance', 75000)->where('positions.items.1.balance', 70000)->where('annual.loanNet', -5000)->etc());
        $this->get('/uzleti-riport?from=2026-01-01&to=2026-12-31')->assertInertia(fn ($p) => $p->where('businessReport.totals.all.income', 0)->where('businessReport.totals.all.expense', 0)->etc());
    }

    public function test_partial_and_full_repayments_retain_history_and_opening_is_not_a_cash_flow(): void
    {
        $id = $this->position('receivable', 100000);
        $this->payment($id, 'loan_given_return', 30000);
        $this->payment($id, 'loan_given_return', 70000);
        $r = app(Positions::class)->report('2026-10-31');
        $this->assertSame(0, $r['items'][0]['balance']);
        $this->assertCount(2, $r['items'][0]['payments']);
        $this->assertDatabaseCount('transactions', 2);
        $this->assertSame(100000, $r['items'][0]['opening_amount']);
    }

    public function test_overpayment_and_retroactive_delete_edit_restore_cannot_break_later_repayments(): void
    {
        $id = $this->position();
        $advance = $this->payment($id, 'loan_given', 100000);
        $repayment = $this->payment($id, 'loan_given_return', 80000, ['date' => '2026-10-10']);
        $this->post("/nyilvantartasok/$id/mozgas", ['client_id' => (string) Str::uuid(), 'date' => '2026-09-01', 'type' => 'loan_given_return', 'amount' => 1, 'account_id' => 1, 'business' => false])->assertSessionHasErrors('position');
        $this->delete("/tetelek/$advance->id")->assertSessionHasErrors('position');
        $this->put("/tetelek/$advance->id", [...$advance->toArray(), 'amount' => 70000])->assertSessionHasErrors('position');
        $this->delete("/tetelek/$repayment->id")->assertSessionHasNoErrors();
        $this->delete("/tetelek/$advance->id")->assertSessionHasNoErrors();
        $this->post("/tetelek/$repayment->id/visszavonas")->assertSessionHasErrors('position');
        $this->post("/tetelek/$advance->id/visszavonas")->assertSessionHasNoErrors();
        $this->post("/tetelek/$repayment->id/visszavonas")->assertSessionHasNoErrors();
        $this->assertSame(20000, app(Positions::class)->report('2026-10-31')['items'][0]['balance']);
    }

    public function test_loan_type_and_account_restrictions_and_idempotent_payments(): void
    {
        $id = $this->position();
        $tx = $this->payment($id, 'loan_given', 12345);
        $this->post("/nyilvantartasok/$id/mozgas", $tx->toArray())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('transactions', 1);
        $this->post('/tetelek', [...$tx->toArray(), 'client_id' => (string) Str::uuid()])->assertSessionHasErrors('type');
        $this->put("/tetelek/$tx->id", [...$tx->toArray(), 'type' => 'expense', 'category_id' => 1])->assertSessionHasErrors('type');
        $this->put("/tetelek/$tx->id", [...$tx->toArray(), 'account_id' => 6])->assertSessionHasErrors('account_id');
    }

    public function test_reserve_target_is_six_times_explicit_monthly_income_and_ors_cannot_be_assigned(): void
    {
        $id = $this->position('reserve', 50000);
        $this->payment($id, 'saving', 20000);
        $this->payment($id, 'saving_return', 10000);
        $this->get('/megtakaritasi-celok?year=2026&month=10')->assertOk()->assertInertia(fn ($p) => $p->where('positions.items.0.target', 4200000)->where('positions.items.0.balance', 60000)->where('positions.items.0.remaining', 4140000)->where('report.actualSavings', 10000)->where('report.cf.numerator', '0')->where('account.balance', -10000)->etc());
        $tx = Transaction::create(['client_id' => (string) Str::uuid(), 'date' => '2026-10-01', 'type' => 'child_saving', 'family_type' => 'child_saving', 'amount' => 500, 'account_id' => 1, 'target_account_id' => 8, 'business' => false]);
        $this->post("/nyilvantartasok/$id/kapcsolat", ['transaction_id' => $tx->id])->assertSessionHasErrors('position');
    }

    public function test_existing_savings_link_once_and_internal_reallocation_does_not_move_cash_or_inflate_savings(): void
    {
        $a = $this->position('reserve');
        $b = $this->position('project');
        $uuid = (string) Str::uuid();
        $this->post('/tetelek', ['client_id' => $uuid, 'date' => '2026-10-01', 'type' => 'saving', 'amount' => 50000, 'account_id' => 1, 'business' => false])->assertSessionHasNoErrors();
        $tx = Transaction::where('client_id', $uuid)->first();
        $this->post("/nyilvantartasok/$a/kapcsolat", ['transaction_id' => $tx->id])->assertSessionHasNoErrors();
        $this->post("/nyilvantartasok/$a/kapcsolat", ['transaction_id' => $tx->id])->assertSessionHasNoErrors();
        $this->post("/nyilvantartasok/$b/kapcsolat", ['transaction_id' => $tx->id])->assertUnprocessable();
        $data = ['client_id' => (string) Str::uuid(), 'from_id' => $a, 'to_id' => $b, 'date' => '2026-10-02', 'amount' => 20000];
        $this->post('/celok/atcsoportositas', $data)->assertSessionHasNoErrors();
        $this->post('/celok/atcsoportositas', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('goal_transfers', 1);
        $this->assertDatabaseCount('transactions', 1);
        $this->get('/megtakaritasi-celok?year=2026&month=10')->assertInertia(fn ($p) => $p->where('positions.items.0.balance', 30000)->where('positions.items.1.balance', 20000)->where('report.actualSavings', 50000)->where('report.cf.numerator', '0')->where('account.balance', -50000)->etc());
        $this->post("/nyilvantartasok/$a/kapcsolat", ['transaction_id' => $tx->id, 'unlink' => true])->assertSessionHasErrors('position');
        $withdraw = $this->payment($b, 'saving_return', 15000, ['date' => '2026-10-03']);
        $transfer = DB::table('goal_transfers')->first();
        $this->delete("/celok/atcsoportositas/$transfer->id")->assertSessionHasErrors('position');
        $this->delete("/tetelek/$withdraw->id")->assertSessionHasNoErrors();
        $this->delete("/celok/atcsoportositas/$transfer->id")->assertSessionHasNoErrors();
    }

    public function test_position_history_respects_selected_period_and_metadata_edits_protect_movements(): void
    {
        $id = $this->position('project');
        $this->payment($id, 'saving', 1000);
        $this->assertSame(0, app(Positions::class)->report('2025-12-31')['items'][0]['balance']);
        $this->assertCount(0, app(Positions::class)->report('2026-09-30')['items'][0]['payments']);
        $p = (array) DB::table('finance_positions')->find($id);
        $this->put("/nyilvantartasok/$id", [...$p, 'start_date' => '2026-10-02'])->assertSessionHasErrors('position');
        $this->put("/nyilvantartasok/$id", [...$p, 'kind' => 'reserve'])->assertUnprocessable();
    }

    public function test_current_and_legacy_backups_restore_without_dropping_authentication(): void
    {
        Storage::fake('local');
        $backup = app(FinanceBackup::class);
        $legacyTables = collect(FinanceBackup::BASE_TABLES)->mapWithKeys(fn ($t) => [$t => DB::table($t)->get()->map(fn ($r) => (array) $r)->all()])->all();
        $legacy = Crypt::encryptString(gzencode(json_encode(['format' => 'buksza-finance-1', 'schema' => hash('sha256', json_encode(array_map(fn ($t) => Schema::getColumnListing($t), FinanceBackup::BASE_TABLES))), 'tables' => $legacyTables])));
        $id = $this->position('reserve');
        $this->payment($id, 'saving', 12345);
        $saved = $backup->decode($backup->create());
        $backup->restore($backup->decode($legacy));
        $this->assertDatabaseCount('finance_positions', 0);
        $backup->restore($saved);
        $this->assertSame(12345, app(Positions::class)->report('2026-10-31')['items'][0]['balance']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_new_pages_and_position_mutations_require_authentication(): void
    {
        auth()->logout();
        foreach (['/kintlevosegeim', '/megtakaritasi-celok'] as $url) {
            $this->get($url)->assertRedirect('/belepes');
        }
        $this->post('/nyilvantartasok', [])->assertRedirect('/belepes');
        $this->post('/celok/atcsoportositas', [])->assertRedirect('/belepes');
    }

    public function test_in_place_goal_allocation_keeps_bank_balance_and_turnover_and_survives_unlink_edit(): void
    {
        $id = $this->position('reserve');
        $a = $this->payment($id, 'saving', 50000, ['target_account_id' => 1]);
        $this->payment($id, 'saving_return', 10000, ['target_account_id' => 1]);
        $this->get('/szamlak?year=2026&month=10')->assertInertia(fn ($p) => $p->where('account.balance', 0)->where('accounts.0.inflow', 0)->where('accounts.0.outflow', 0)->where('report.actualSavings', 40000)->etc());
        // Unlink return first, then deposit: financial entries and allocation remain editable.
        $return = Transaction::where('type', 'saving_return')->first();
        $this->post("/nyilvantartasok/$id/kapcsolat", ['transaction_id' => $return->id, 'unlink' => true])->assertSessionHasNoErrors();
        $this->post("/nyilvantartasok/$id/kapcsolat", ['transaction_id' => $a->id, 'unlink' => true])->assertSessionHasNoErrors();
        $this->put("/tetelek/$a->id", [...$a->toArray(), 'amount' => 60000])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_exports_include_principal_flows_and_goal_assignments_without_recounting_money(): void
    {
        $id = $this->position('payable');
        $this->payment($id, 'loan_received', 12345);
        $sheets = app(AnnualExport::class)->sheets(2026);
        $this->assertSame(12345, $sheets['Nyilvántartások'][1][4]);
        $this->assertSame([0, 0, 12345, 0], array_slice($sheets['Hónapok'][10], -4));
        $this->get('/export?year=2026&format=csv&sheet=Nyilvántartások')->assertOk()->assertSee('Teszt cél / partner');
    }

    public function test_recurring_restore_cannot_bypass_the_linked_goal_balance_check(): void
    {
        $id = $this->position('project', 100000);
        $tx = $this->payment($id, 'saving_return', 90000);
        $this->post('/rendszeres', ['name' => 'Visszavétel', 'active' => true, 'frequency' => 'monthly', 'interval' => 1, 'due_day' => 10, 'due_month' => 1, 'start_date' => '2026-01-01', 'effective_date' => '2026-01-01', 'loan_terms' => false, 'payload' => ['type' => 'saving_return', 'amount' => 90000, 'account_id' => 1, 'business' => false]])->assertSessionHasNoErrors();
        $template = DB::table('recurring_templates')->first();
        $data = ['due_date' => '2026-10-10', 'date' => '2026-10-10', 'amount' => 90000];
        $this->post("/rendszeres/$template->id/teljesites", [...$data, 'existing_id' => $tx->id])->assertSessionHasNoErrors();
        $this->delete("/tetelek/$tx->id")->assertSessionHasNoErrors();
        $this->payment($id, 'saving_return', 20000, ['date' => '2026-10-02']);
        $this->post("/rendszeres/$template->id/teljesites", $data)->assertSessionHasErrors('position');
        $this->assertSame(80000, app(Positions::class)->report('2026-10-31')['items'][0]['balance']);
    }
}
