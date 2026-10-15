<?php

namespace Tests\Feature;

use App\Finance\Insights;
use App\Models\PushDevice;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\PushTransport;
use App\Notifications\ReminderDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PhaseSixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(19, 0));
        $this->actingAs(User::factory()->create());
    }

    private function payment(string $type, int $amount, bool $business = false, array $extra = []): Transaction
    {
        $this->post('/tetelek', ['client_id' => (string) Str::uuid(), 'date' => '2026-10-15', 'type' => $type, 'amount' => $amount, 'business' => $business, 'category_id' => $type === 'expense' ? 1 : null, 'account_id' => 1, 'source' => 'Munka', ...$extra])->assertSessionHasNoErrors();

        return Transaction::latest('id')->first();
    }

    public function test_family_alerts_ignore_person_filters_and_follow_edits_deletion_and_undo(): void
    {
        $this->get('/?year=2026&month=10');
        DB::table('monthly_plans')->where('year', 2026)->where('month', 10)->where('category_id', 1)->update(['amount' => 1000]);
        $tx = $this->payment('expense', 800);
        $this->get('/?year=2026&month=10&person=Anna')->assertInertia(fn (Assert $p) => $p->where('insights.budgets.0.budgetState', 'near')->where('insights.budgets.0.spent', 800)->where('scopedReport.expense', 0));
        $this->delete('/tetelek/'.$tx->id);
        $this->get('/?year=2026&month=10')->assertInertia(fn (Assert $p) => $p->has('insights.budgets', 0));
        $this->post('/tetelek/'.$tx->id.'/visszavonas');
        $this->get('/?year=2026&month=10')->assertInertia(fn (Assert $p) => $p->where('insights.budgets.0.budgetState', 'near'));
    }

    public function test_business_report_filters_and_exports_reconcile_and_do_not_change_the_family_cf(): void
    {
        $this->payment('income', 10000, true);
        $business = $this->payment('expense', 1000, true, ['note' => '=not-a-formula']);
        $this->payment('expense', 2000);
        $this->payment('transfer', 100, true, ['target_account_id' => 3, 'fee_amount' => 5]);
        $this->payment('child_saving', 500, false, ['target_account_id' => 8]);
        $before = app(Insights::class)->onDate('2026-10-15');
        $path = '/uzleti-riport?from=2026-10-01&to=2026-10-31&group=business';
        $this->get($path)->assertInertia(fn (Assert $p) => $p->where('view', 'business')->where('businessReport.totals.all.expense', 3005)->where('businessReport.totals.business.expense', 1005)->where('businessReport.totals.personal.expense', 2000)->has('businessReport.rows', 3)->where('report.cf.exact', '5495'));
        $this->get('/uzleti-riport?from=2026-10-01&to=2026-10-31&group=personal')->assertInertia(fn (Assert $p) => $p->has('businessReport.rows', 1));
        $csv = $this->get('/uzleti-riport/export?from=2026-10-01&to=2026-10-31&group=business&format=csv')->assertOk();
        self::assertStringContainsString("'=not-a-formula", $csv->getContent());
        self::assertStringContainsString('Banki díj', $csv->getContent());
        $xlsx = $this->get('/uzleti-riport/export?from=2026-10-01&to=2026-10-31&group=business&format=xlsx')->assertOk();
        self::assertSame('PK', substr($xlsx->getContent(), 0, 2));
        self::assertEquals($before, app(Insights::class)->onDate('2026-10-15'));
        $this->delete('/tetelek/'.$business->id);
        $this->get($path)->assertInertia(fn (Assert $p) => $p->where('businessReport.totals.business.expense', 5));
        $this->post('/tetelek/'.$business->id.'/visszavonas');
        $this->get($path)->assertInertia(fn (Assert $p) => $p->where('businessReport.totals.business.expense', 1005));
    }

    public function test_report_date_validation_and_access_are_enforced(): void
    {
        $this->get('/uzleti-riport?from=2026-02-30&to=2026-10-01')->assertSessionHasErrors('from');
        $this->get('/uzleti-riport?from=2026-10-15&to=2026-10-01')->assertSessionHasErrors('to');
        $this->get('/uzleti-riport/export?format=csv&group=unknown')->assertSessionHasErrors('group');
        auth()->logout();
        $this->get('/uzleti-riport')->assertRedirect('/belepes');
        $this->get('/uzleti-riport/export?format=xlsx')->assertRedirect('/belepes');
    }

    public function test_evening_insights_use_default_plans_without_writing_and_exclude_future_payments(): void
    {
        DB::table('categories')->where('id', 1)->update(['default_amount' => 1000]);
        $this->payment('expense', 800, false, ['date' => '2027-01-01']);
        $this->payment('expense', 9000, false, ['date' => '2027-01-02']);
        $plans = DB::table('monthly_plans')->count();
        $settings = DB::table('year_settings')->count();
        $insights = app(Insights::class)->onDate('2027-01-01');
        self::assertSame('near', $insights['budgets'][0]['budgetState']);
        self::assertSame(800, $insights['budgets'][0]['spent']);
        self::assertSame(1000, $insights['budgets'][0]['available']);
        self::assertSame($plans, DB::table('monthly_plans')->count());
        self::assertSame($settings, DB::table('year_settings')->count());
    }

    public function test_unavailable_optional_insights_do_not_block_the_daily_notification(): void
    {
        PushDevice::create(['device_id' => (string) Str::uuid(), 'user_id' => auth()->id(), 'name' => 'iPhone', 'endpoint_hash' => hash('sha256', 'summary-failure'), 'subscription' => [], 'enabled' => true]);
        DB::table('reminder_settings')->insert(['user_id' => auth()->id(), 'daily_enabled' => true, 'anna_enabled' => false, 'daily_time' => '19:00', 'anna_time' => '19:00', 'insights_enabled' => true]);
        // Simulate an unavailable reporting table only in the guarded in-memory database.
        Schema::drop('monthly_plans');
        $fake = new class implements PushTransport
        {
            public array $calls = [];

            public function send(PushDevice $device, array $payload): array
            {
                $this->calls[] = $payload;

                return ['success' => true, 'expired' => false];
            }
        };
        self::assertSame(1, (new ReminderDispatcher($fake))->dispatch(new \DateTimeImmutable('2026-10-15 19:00 Europe/Budapest')));
        self::assertCount(1, $fake->calls);
        self::assertStringContainsString('Szánj egy percet', $fake->calls[0]['body']);
        self::assertStringNotContainsString('Figyelmeztetések:', $fake->calls[0]['body']);
    }

    public function test_evening_summary_is_opt_in_contains_counts_only_and_keeps_single_delivery(): void
    {
        $device = PushDevice::create(['device_id' => (string) Str::uuid(), 'user_id' => auth()->id(), 'name' => 'iPhone', 'endpoint_hash' => hash('sha256', 'phase-six'), 'subscription' => [], 'enabled' => true]);
        $this->payment('expense', 9876, true, ['note' => 'private-transaction-name']);
        $this->post('/ertesitesek', ['daily_enabled' => true, 'anna_enabled' => false, 'daily_time' => '19:00', 'anna_time' => '19:00', 'insights_enabled' => true])->assertSessionHasNoErrors();
        $fake = new class implements PushTransport
        {
            public array $calls = [];

            public function send(PushDevice $device, array $payload): array
            {
                $this->calls[] = $payload;

                return ['success' => true, 'expired' => false];
            }
        };
        $dispatcher = new ReminderDispatcher($fake);
        $now = new \DateTimeImmutable('2026-10-15 19:00 Europe/Budapest');
        self::assertSame(1, $dispatcher->dispatch($now));
        self::assertSame(0, $dispatcher->dispatch($now->modify('+1 minute')));
        self::assertCount(1, $fake->calls);
        self::assertStringContainsString('1 kategóriakeret', $fake->calls[0]['body']);
        self::assertStringNotContainsString('9876', $fake->calls[0]['body']);
        self::assertStringNotContainsString('private-transaction-name', $fake->calls[0]['body']);
        $this->post('/ertesitesek', ['daily_enabled' => true, 'anna_enabled' => false, 'daily_time' => '19:00', 'anna_time' => '19:00', 'insights_enabled' => false]);
        self::assertSame(1, $dispatcher->dispatch($now->modify('+1 day')));
        self::assertStringNotContainsString('Figyelmeztetések:', $fake->calls[1]['body']);
        self::assertSame($device->id, DB::table('push_deliveries')->first()->device_id);
    }
}
