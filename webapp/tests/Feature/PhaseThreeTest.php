<?php

namespace Tests\Feature;

use App\Finance\PlanSuggestions;
use App\Models\PushDevice;
use App\Models\RecurringTemplate;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\PushTransport;
use App\Notifications\ReminderDispatcher;
use App\Notifications\WebPushTransport;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Minishlink\WebPush\VAPID;
use Tests\TestCase;

class PhaseThreeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 14)->setTime(18, 0));
        $this->actingAs(User::factory()->create());
    }

    private function template(array $extra = []): RecurringTemplate
    {
        $this->post('/rendszeres', [...[
            'name' => 'Örs havi MÁK', 'active' => true, 'frequency' => 'monthly', 'interval' => 1, 'due_day' => 10, 'due_month' => 1,
            'start_date' => '2026-01-01', 'effective_date' => '2026-01-01', 'end_date' => null, 'loan_terms' => false,
            'payload' => ['type' => 'child_saving', 'amount' => '15000', 'account_id' => 1, 'target_account_id' => 8, 'category_id' => null, 'loan_id' => null, 'business' => false, 'fee_amount' => 0],
        ], ...$extra])->assertSessionHasNoErrors();

        return RecurringTemplate::latest('id')->first();
    }

    public function test_templates_are_unpaid_until_explicit_confirmation_and_double_click_does_not_duplicate(): void
    {
        $t = $this->template();
        $this->assertDatabaseCount('transactions', 0);
        $data = ['due_date' => '2026-10-10', 'date' => '2026-10-12', 'amount' => 16000];
        $this->post("/rendszeres/$t->id/teljesites", $data)->assertSessionHasNoErrors();
        $this->post("/rendszeres/$t->id/teljesites", $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('transactions', 1);
        $tx = Transaction::first();
        self::assertSame(16000, $tx->amount);
        $this->get('/?year=2026&month=10')->assertInertia(fn (Assert $p) => $p->where('report.childSavings', 16000)->where('report.expense', 0)->where('report.actualSavings', 0)->where('recurring.occurrences.0.paid', true));
        $this->delete('/tetelek/'.$tx->id);
        $this->post("/rendszeres/$t->id/teljesites", $data)->assertSessionHasNoErrors();
        self::assertSame(1, Transaction::count());
        $this->assertDatabaseCount('transactions', 1);
        $this->post('/tetelek/'.$tx->id.'/visszavonas');
        self::assertSame(0, Transaction::count());
        $this->post('/tetelek/'.$tx->id.'/visszavonas');
        self::assertSame(1, Transaction::count());
        $this->post("/rendszeres/$t->id/teljesites", [...$data, 'date' => '2026-10-15'])->assertSessionHasErrors('date');
        $this->post("/rendszeres/$t->id/teljesites", [...$data, 'due_date' => '2026-10-11'])->assertSessionHasErrors('due_date');
    }

    public function test_linking_existing_payment_preserves_amount_date_and_cashflow(): void
    {
        $t = $this->template();
        $this->post('/tetelek', [...$t->data['payload'], 'client_id' => (string) Str::uuid(), 'date' => '2026-10-09', 'amount' => 17000])->assertSessionHasNoErrors();
        $tx = Transaction::first();
        $this->post("/rendszeres/$t->id/teljesites", ['due_date' => '2026-10-10', 'date' => '2026-10-12', 'amount' => 15000, 'existing_id' => $tx->id])->assertSessionHasNoErrors()->assertSessionMissing('undoId');
        self::assertSame(17000, $tx->refresh()->amount);
        self::assertSame('2026-10-09', $tx->date);
        $this->assertDatabaseCount('transactions', 1);
        $this->get('/?year=2026&month=10')->assertInertia(fn (Assert $p) => $p->where('report.childSavings', 17000));
    }

    public function test_template_changes_keep_past_versions_and_can_be_stopped(): void
    {
        $t = $this->template();
        $this->put("/rendszeres/$t->id", [...$t->data, 'effective_date' => '2026-10-01'])->assertSessionHasErrors('effective_date');
        $this->put("/rendszeres/$t->id", [...$t->data, 'due_day' => 20, 'effective_date' => '2026-10-15'])->assertSessionHasErrors('effective_date');
        $this->put("/rendszeres/$t->id", [...$t->data, 'payload' => [...$t->data['payload'], 'amount' => 20000], 'effective_date' => '2026-11-01'])->assertSessionHasNoErrors();
        $this->get('/rendszeres?year=2026&month=10')->assertInertia(fn (Assert $p) => $p->where('recurring.occurrences.0.payload.amount', 15000));
        $this->get('/rendszeres?year=2026&month=11')->assertInertia(fn (Assert $p) => $p->where('recurring.occurrences.0.payload.amount', 20000));
        $this->put("/rendszeres/$t->id", [...$t->refresh()->data, 'active' => false, 'effective_date' => '2026-12-01'])->assertSessionHasNoErrors();
        $this->get('/rendszeres?year=2026&month=12')->assertInertia(fn (Assert $p) => $p->has('recurring.occurrences', 0));
    }

    public function test_complete_months_and_exact_cross_year_suggestions_and_mutation_invalidation(): void
    {
        foreach ([['year' => 2025, 'month' => 12], ['year' => 2026, 'month' => 1], ['year' => 2026, 'month' => 2]] as $period) {
            DB::table('month_observations')->insert([...$period, 'complete' => true]);
        }
        $transactions = [['date' => '2025-12-12', 'type' => 'expense', 'category_id' => 1, 'amount' => 100], ['date' => '2026-01-12', 'type' => 'expense', 'category_id' => 1, 'amount' => 200], ['date' => '2026-02-12', 'type' => 'transfer', 'category_id' => null, 'amount' => 9999, 'fee_amount' => 1, 'fee_category_id' => 18]];
        $calc = new PlanSuggestions;
        $rows = DB::table('month_observations')->get()->map(fn ($r) => (array) $r)->all();
        $result = $calc->calculate(2026, 3, [['id' => 1], ['id' => 18]], $transactions, $rows);
        self::assertSame([], $result['missing']);
        self::assertSame('100', $result['amounts'][1]['numerator']);
        self::assertSame('1', $result['amounts'][1]['denominator']);
        self::assertSame('1/3', $result['amounts'][18]['exact']);
        self::assertStringContainsString('…', $result['amounts'][18]['text']);
        self::assertSame(['2025-12'], $calc->calculate(2026, 3, [['id' => 1]], $transactions, array_slice($rows, 1))['missing']);
        $this->post('/honap-teljes', ['year' => 2026, 'month' => 10, 'complete' => true])->assertSessionHasErrors('month');
        $this->post('/honap-teljes', ['year' => 2026, 'month' => 9, 'complete' => true])->assertSessionHasNoErrors();
        $tx = Transaction::create(['client_id' => (string) Str::uuid(), 'date' => '2026-09-12', 'type' => 'expense', 'amount' => 10, 'category_id' => 1, 'account_id' => 1, 'business' => false]);
        self::assertFalse((bool) DB::table('month_observations')->where('month', 9)->value('complete'));
        $this->post('/honap-teljes', ['year' => 2026, 'month' => 9, 'complete' => true]);
        $this->post('/honap-teljes', ['year' => 2026, 'month' => 8, 'complete' => true]);
        $tx->update(['date' => '2026-08-31']);
        self::assertSame(0, DB::table('month_observations')->whereIn('month', [8, 9])->where('complete', true)->count());
        $this->post('/honap-teljes', ['year' => 2026, 'month' => 8, 'complete' => true]);
        $tx->delete();
        self::assertFalse((bool) DB::table('month_observations')->where('month', 8)->value('complete'));
    }

    private function device(): PushDevice
    {
        return PushDevice::create(['device_id' => (string) Str::uuid(), 'user_id' => auth()->id(), 'name' => 'iPhone', 'endpoint_hash' => hash('sha256', 'test'), 'subscription' => ['endpoint' => 'https://web.push.apple.com/test', 'keys' => ['p256dh' => str_repeat('a', 87), 'auth' => str_repeat('b', 22)]], 'enabled' => true]);
    }

    public function test_reminder_delivery_is_combined_idempotent_and_does_not_expose_credentials(): void
    {
        $device = $this->device();
        $raw = DB::table('push_devices')->value('subscription');
        self::assertStringNotContainsString('push.apple.com', $raw);
        DB::table('reminder_settings')->insert(['user_id' => auth()->id(), 'daily_enabled' => true, 'anna_enabled' => true, 'daily_time' => '19:00', 'anna_time' => '19:00']);
        $fake = new class implements PushTransport
        {
            public array $calls = [];

            public function send(PushDevice $device, array $payload): array
            {
                $this->calls[] = $payload;

                return ['success' => true, 'expired' => false];
            }
        };
        $service = new ReminderDispatcher($fake);
        $time = new \DateTimeImmutable('2026-10-31 18:00:00 UTC');
        self::assertSame(1, $service->dispatch($time));
        self::assertSame(0, $service->dispatch($time->modify('+1 minute')));
        self::assertCount(1, $fake->calls);
        self::assertStringContainsString('Anna', $fake->calls[0]['body']);
        self::assertStringContainsString('/esti-rogzites', $fake->calls[0]['url']);
        $this->assertDatabaseCount('push_deliveries', 2);
        $this->get('/beallitasok')->assertInertia(fn (Assert $p) => $p->has('push.devices', 1)->missing('push.devices.0.subscription')->missing('push.devices.0.endpoint_hash')->missing('push.private_key'));
        $this->delete('/ertesitesek/eszkoz/'.$device->id)->assertSessionHasNoErrors();
        self::assertFalse($device->refresh()->enabled);
    }

    public function test_failed_and_expired_delivery_is_recorded_and_does_not_repeat(): void
    {
        $device = $this->device();
        DB::table('reminder_settings')->insert(['user_id' => auth()->id(), 'daily_time' => '19:00', 'anna_time' => '19:00']);
        $fake = new class implements PushTransport
        {
            public function send(PushDevice $device, array $payload): array
            {
                return ['success' => false, 'expired' => true];
            }
        };
        $service = new ReminderDispatcher($fake);
        self::assertSame(0, $service->dispatch(new \DateTimeImmutable('2026-10-14 17:00:00 UTC')));
        self::assertFalse($device->refresh()->enabled);
        self::assertSame('failed', $device->last_status);
        $this->assertDatabaseHas('push_deliveries', ['kind' => 'daily', 'status' => 'failed']);
        self::assertSame(0, $service->dispatch(new \DateTimeImmutable('2026-10-14 17:01:00 UTC')));
        $this->assertDatabaseCount('push_deliveries', 1);
    }

    public function test_push_registration_requires_https_provider_and_ownership_and_keys_survive_rerun(): void
    {
        $this->artisan('buksza:push-keys')->assertSuccessful();
        $credentials = DB::table('push_credentials')->first();
        self::assertNotSame(Crypt::decryptString($credentials->private_key), $credentials->private_key);
        $this->artisan('buksza:push-keys')->assertSuccessful();
        self::assertSame($credentials->public_key, DB::table('push_credentials')->value('public_key'));
        config(['app.url' => 'https://buksza.example.test']);
        $data = ['device_id' => (string) Str::uuid(), 'name' => 'iPhone', 'subscription' => ['endpoint' => 'http://127.0.0.1/private', 'keys' => ['p256dh' => str_repeat('a', 87), 'auth' => str_repeat('b', 22)]]];
        $this->post('/ertesitesek/eszkoz', $data)->assertSessionHasErrors('subscription')->assertSessionMissing('_old_input.subscription');
        $data['subscription']['endpoint'] = 'https://web.push.apple.com/token';
        $this->post('/ertesitesek/eszkoz', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('push_devices', 1);
        $this->post('/ertesitesek/eszkoz', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('push_devices', 1);
        $device = PushDevice::first();
        $this->actingAs(User::factory()->create());
        $this->delete('/ertesitesek/eszkoz/'.$device->id)->assertForbidden();
        $this->post('/ertesitesek/eszkoz/'.$device->id.'/proba')->assertForbidden();
    }

    public function test_evening_date_selects_its_actual_month_and_never_loads_scope_filters(): void
    {
        $this->get('/esti-rogzites?date=2026-08-31&account_id=2')->assertInertia(fn (Assert $p) => $p->where('view', 'evening')->where('eveningDate', '2026-08-31')->where('month', 8)->where('filters.account_id', null));
        $this->get('/esti-rogzites?date=2026-10-15')->assertSessionHasErrors('date');
    }

    public function test_real_push_library_encrypts_payload_and_recognizes_expired_subscriptions_without_network(): void
    {
        config(['app.url' => 'https://buksza.example.test']);
        $this->artisan('buksza:push-keys')->assertSuccessful();
        $receiver = VAPID::createVapidKeys();
        $device = $this->device();
        $device->update(['subscription' => ['endpoint' => 'https://web.push.apple.com/test', 'keys' => ['p256dh' => $receiver['publicKey'], 'auth' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=')]]]);
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(201), new Response(410)]));
        $stack->push(Middleware::history($history));
        $transport = new WebPushTransport(new Client(['handler' => $stack]));
        $payload = ['title' => 'Buksza', 'body' => 'Próbaértesítés', 'url' => '/esti-rogzites'];
        self::assertSame(['success' => true, 'expired' => false], $transport->send($device->refresh(), $payload));
        self::assertSame(['success' => false, 'expired' => true], $transport->send($device, $payload));
        self::assertStringNotContainsString('Próbaértesítés', (string) $history[0]['request']->getBody());
        self::assertSame('aes128gcm', $history[0]['request']->getHeaderLine('Content-Encoding'));
        self::assertSame('600', $history[0]['request']->getHeaderLine('TTL'));
        self::assertStringContainsString('vapid', $history[0]['request']->getHeaderLine('Authorization'));
    }
}
