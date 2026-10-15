<?php

namespace Tests\Feature;

use App\Models\PushDevice;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\PushTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InstantBudgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
        $this->actingAs(User::factory()->create());
        $this->get('/?year=2026&month=10');
        config(['app.url' => 'https://buksza.example.test']);
        $this->artisan('buksza:push-keys')->assertSuccessful();
        PushDevice::create(['device_id' => (string) Str::uuid(), 'user_id' => auth()->id(), 'name' => 'iPhone', 'endpoint_hash' => hash('sha256', 'instant-test'), 'subscription' => [], 'enabled' => true]);
        DB::table('reminder_settings')->insert(['user_id' => auth()->id(), 'daily_enabled' => true, 'anna_enabled' => true, 'daily_time' => '19:00', 'anna_time' => '19:00', 'instant_budget_enabled' => true]);
        DB::table('monthly_plans')->where('year', 2026)->where('month', 10)->where('category_id', 1)->update(['amount' => 1000]);
    }

    private function transport(): PushTransport
    {
        $fake = new class implements PushTransport
        {
            public array $calls = [];

            public bool $fail = false;

            public function send(PushDevice $device, array $payload): array
            {
                $this->calls[] = $payload;
                if ($this->fail) {
                    throw new \RuntimeException('Transport unavailable');
                }

                return ['success' => true, 'expired' => false];
            }
        };
        $this->app->instance(PushTransport::class, $fake);

        return $fake;
    }

    private function payment(int $amount, array $extra = []): Transaction
    {
        $this->post('/tetelek', ['client_id' => (string) Str::uuid(), 'date' => '2026-10-15', 'type' => 'expense', 'amount' => $amount, 'category_id' => 1, 'account_id' => 1, 'business' => false, ...$extra])->assertSessionHasNoErrors();

        return Transaction::latest('id')->first();
    }

    public function test_exact_reach_sends_immediately_once_per_device_category_month_and_undo_does_not_repeat(): void
    {
        $fake = $this->transport();
        $this->payment(800);
        self::assertCount(0, $fake->calls);
        $tx = $this->payment(200);
        self::assertCount(1, $fake->calls);
        self::assertSame('Kategóriakeret elérve', $fake->calls[0]['title']);
        self::assertSame('/honap?year=2026&month=10', $fake->calls[0]['url']);
        self::assertStringNotContainsString('1000', $fake->calls[0]['body']);
        $this->delete('/tetelek/'.$tx->id);
        $this->post('/tetelek/'.$tx->id.'/visszavonas');
        $this->payment(1);
        self::assertCount(1, $fake->calls);
        $this->assertDatabaseHas('push_deliveries', ['date' => '2026-10-01', 'kind' => 'budget:1', 'status' => 'sent']);
        $this->get('/beallitasok')->assertInertia(fn (Assert $p) => $p->where('systemStatus.deliveries.0.kind', 'budget:1')->where('systemStatus.deliveries.0.category_name', 'élelmiszer'));
    }

    public function test_crossing_by_edit_and_failed_push_preserve_the_saved_financial_record(): void
    {
        $fake = $this->transport();
        $tx = $this->payment(900);
        $fake->fail = true;
        $this->put('/tetelek/'.$tx->id, [...$tx->toArray(), 'amount' => 1001])->assertSessionHasNoErrors();
        self::assertSame(1001, $tx->refresh()->amount);
        self::assertCount(1, $fake->calls);
        self::assertSame('Túllépted a kategóriakeretet', $fake->calls[0]['title']);
        $this->assertDatabaseHas('push_deliveries', ['kind' => 'budget:1', 'status' => 'failed']);
        $this->put('/tetelek/'.$tx->id, [...$tx->toArray(), 'amount' => 1002])->assertSessionHasNoErrors();
        self::assertCount(1, $fake->calls);
    }

    public function test_historical_future_and_disabled_notifications_do_not_send_but_daily_can_stay_enabled(): void
    {
        $fake = $this->transport();
        $this->payment(1100, ['date' => '2026-09-15']);
        $this->payment(1100, ['date' => '2026-10-16']);
        self::assertCount(0, $fake->calls);
        DB::table('reminder_settings')->update(['instant_budget_enabled' => false]);
        $this->payment(1100);
        self::assertCount(0, $fake->calls);
        self::assertTrue((bool) DB::table('reminder_settings')->value('daily_enabled'));
    }

    public function test_pocket_funding_and_transfer_fee_can_cross_their_own_budgets_once(): void
    {
        $fake = $this->transport();
        DB::table('monthly_plans')->where('year', 2026)->where('month', 10)->whereIn('category_id', [18, 21])->update(['amount' => 100]);
        $this->payment(100, ['type' => 'transfer', 'category_id' => null, 'target_account_id' => 4, 'fee_amount' => 100]);
        self::assertCount(2, $fake->calls);
        self::assertSame(2, DB::table('push_deliveries')->where('kind', 'like', 'budget:%')->count());
    }
}
