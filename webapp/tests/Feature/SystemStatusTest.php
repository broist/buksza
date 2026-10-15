<?php

namespace Tests\Feature;

use App\Models\PushDevice;
use App\Models\User;
use App\Operations\SystemStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SystemStatusTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::factory()->create());
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00 UTC'));
        $this->directory = sys_get_temp_dir().'/buksza-status-test-'.Str::uuid();
        File::makeDirectory($this->directory);
        config(['operations.backup_status_directory' => $this->directory]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function snapshot(): array
    {
        return app(SystemStatus::class)->forUser(auth()->id());
    }

    private function report(string $name, array $overrides = []): void
    {
        File::put($this->directory.'/'.$name, json_encode([
            'version' => 1, 'status' => 'succeeded', 'trigger' => 'manual',
            'started_at' => '2026-10-15T11:59:00Z', 'finished_at' => '2026-10-15T11:59:30Z', 'bytes' => 4096,
            ...$overrides,
        ]));
    }

    public function test_unknown_is_not_reported_as_success_and_status_is_only_on_authenticated_settings(): void
    {
        $status = $this->snapshot();
        self::assertSame('unknown', $status['scheduler']['state']);
        self::assertSame('unknown', $status['backup']['state']);
        self::assertNull($status['backup']['last_scheduled_success']);
        $this->get('/beallitasok')->assertInertia(fn (Assert $p) => $p->where('systemStatus.backup.state', 'unknown')->where('systemStatus.deliveries', []));
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('systemStatus', null));
        auth()->logout();
        $this->get('/beallitasok')->assertRedirect('/belepes');
    }

    public function test_scheduler_freshness_uses_server_time_and_rejects_future_or_old_heartbeats(): void
    {
        foreach ([0 => 'healthy', 180 => 'healthy', 181 => 'stale', -60 => 'stale'] as $seconds => $expected) {
            DB::table('scheduler_heartbeats')->updateOrInsert(['name' => 'reminders'], ['last_seen_at' => CarbonImmutable::now('UTC')->subSeconds($seconds)->format('Y-m-d H:i:s')]);
            self::assertSame($expected, $this->snapshot()['scheduler']['state']);
        }
        self::assertSame('2026-10-15T12:01:00+00:00', $this->snapshot()['scheduler']['last_seen_at']);
    }

    public function test_manual_success_does_not_claim_scheduled_success_and_failures_keep_previous_success(): void
    {
        $this->report('backup.json');
        $this->report('last-success.json', ['private_key' => 'never-expose', 'path' => '/private/location']);
        self::assertSame('healthy', $this->snapshot()['backup']['state']);
        self::assertNull($this->snapshot()['backup']['last_scheduled_success']);
        self::assertSame('unknown', $this->snapshot()['backup']['scheduled_state']);
        self::assertArrayNotHasKey('private_key', $this->snapshot()['backup']['last_success']);
        self::assertArrayNotHasKey('path', $this->snapshot()['backup']['last_success']);
        $this->report('scheduled-success.json', ['trigger' => 'scheduled']);
        $this->report('backup.json', ['status' => 'failed', 'bytes' => null]);
        $status = $this->snapshot();
        self::assertSame('failed', $status['backup']['state']);
        self::assertSame(4096, $status['backup']['last_success']['bytes']);
        self::assertSame('scheduled', $status['backup']['last_scheduled_success']['trigger']);
        self::assertSame('healthy', $status['backup']['scheduled_state']);
        $this->report('scheduled-success.json', ['trigger' => 'scheduled', 'started_at' => '2026-10-14T03:00:00Z', 'finished_at' => '2026-10-14T03:01:00Z']);
        self::assertSame('stale', $this->snapshot()['backup']['scheduled_state']);
    }

    public function test_stale_and_stalled_backups_and_missing_success_cannot_look_healthy(): void
    {
        $this->report('backup.json');
        self::assertSame('unknown', $this->snapshot()['backup']['state']);
        $this->report('last-success.json', ['started_at' => '2026-10-14T09:00:00Z', 'finished_at' => '2026-10-14T09:01:00Z']);
        self::assertSame('stale', $this->snapshot()['backup']['state']);
        $this->report('backup.json', ['status' => 'running', 'finished_at' => null, 'bytes' => null]);
        self::assertSame('running', $this->snapshot()['backup']['state']);
        $this->report('backup.json', ['status' => 'running', 'started_at' => '2026-10-15T11:29:59Z', 'finished_at' => null, 'bytes' => null]);
        self::assertSame('stalled', $this->snapshot()['backup']['state']);
    }

    public function test_corrupt_future_oversized_or_inconsistent_reports_are_not_trusted(): void
    {
        foreach ([
            ['version' => 2], ['trigger' => 'unexpected'], ['status' => 'ok'], ['bytes' => 0], ['bytes' => '4096'],
            ['started_at' => '2026-02-30T11:59:00Z'], ['finished_at' => '2026-10-16T12:00:00Z'],
            ['finished_at' => '2026-10-15T11:58:00Z'], ['started_at' => 'yesterday'],
        ] as $overrides) {
            $this->report('backup.json', $overrides);
            self::assertSame('unknown', $this->snapshot()['backup']['state']);
        }
        foreach (['{broken', str_repeat('x', 2049)] as $contents) {
            File::put($this->directory.'/backup.json', $contents);
            clearstatcache();
            self::assertSame('unknown', $this->snapshot()['backup']['state']);
        }
        $this->report('scheduled-success.json');
        self::assertNull($this->snapshot()['backup']['last_scheduled_success']);
    }

    public function test_delivery_history_is_bounded_owned_and_has_unambiguous_dates_without_subscriptions(): void
    {
        $own = PushDevice::create(['device_id' => (string) Str::uuid(), 'user_id' => auth()->id(), 'name' => 'Saját telefon', 'endpoint_hash' => hash('sha256', 'own'), 'subscription' => ['endpoint' => 'https://web.push.apple.com/private-token'], 'enabled' => true]);
        $other = PushDevice::create(['device_id' => (string) Str::uuid(), 'user_id' => User::factory()->create()->id, 'name' => 'Másik felhasználó telefonja', 'endpoint_hash' => hash('sha256', 'other'), 'subscription' => [], 'enabled' => true]);
        for ($i = 1; $i <= 25; $i++) {
            DB::table('push_deliveries')->insert(['device_id' => $own->id, 'date' => sprintf('2026-09-%02d', $i), 'kind' => 'daily', 'status' => 'sent', 'attempted_at' => sprintf('2026-09-%02d 19:00:00', $i), 'sent_at' => sprintf('2026-09-%02d 19:00:01', $i)]);
        }
        DB::table('push_deliveries')->insert(['device_id' => $other->id, 'date' => '2026-10-15', 'kind' => 'daily', 'status' => 'failed', 'attempted_at' => '2026-10-15 12:00:00']);
        $history = $this->snapshot()['deliveries'];
        self::assertCount(20, $history);
        self::assertSame('2026-09-25T19:00:00+02:00', $history[0]['attempted_at']);
        self::assertSame(['Saját telefon'], array_values(array_unique(array_column($history, 'device_name'))));
        self::assertStringNotContainsString('private-token', json_encode($history));
        self::assertArrayNotHasKey('subscription', $history[0]);
        $initial = $this->get('/beallitasok');
        $version = $initial->viewData('page')['version'];
        $this->get('/beallitasok', ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Inertia-Partial-Component' => 'Finance', 'X-Inertia-Partial-Data' => 'systemStatus'])
            ->assertOk()->assertJsonPath('props.systemStatus.deliveries.0.device_name', 'Saját telefon')->assertJsonMissingPath('props.transactions');
    }
}
