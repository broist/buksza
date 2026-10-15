<?php

namespace App\Notifications;

use App\Models\PushDevice;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class ReminderDispatcher
{
    public function __construct(private PushTransport $transport) {}

    public function dispatch(DateTimeImmutable $now): int
    {
        DB::table('scheduler_heartbeats')->updateOrInsert(['name' => 'reminders'], ['last_seen_at' => $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')]);
        $sent = 0;
        $planner = new ReminderPlanner;
        foreach (DB::table('reminder_settings')->get() as $settings) {
            $date = $now->setTimezone(new \DateTimeZone('Europe/Budapest'))->format('Y-m-d');
            $reported = DB::table('account_snapshots')->join('accounts', 'accounts.id', '=', 'account_snapshots.account_id')->where('accounts.kind', 'snapshot')->where('accounts.owner', 'Anna')->where('account_snapshots.date', $date)->exists();
            foreach ($planner->due($now, (array) $settings, $reported) as $event) {
                foreach (PushDevice::where('user_id', $settings->user_id)->where('enabled', true)->get() as $device) {
                    $kinds = DB::transaction(function () use ($event, $device) {
                        $available = [];
                        foreach ($event['kinds'] as $kind) {
                            $inserted = DB::table('push_deliveries')->insertOrIgnore(['device_id' => $device->id, 'date' => $event['date'], 'kind' => $kind, 'status' => 'pending', 'attempted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                            if ($inserted) {
                                $available[] = $kind;
                            }
                        }

                        return $available;
                    });
                    if (! $kinds) {
                        continue;
                    }
                    try {
                        $result = $this->transport->send($device, $planner->payload($event['date'], $kinds));
                    } catch (\Throwable) {
                        // Never log subscription tokens or private signing material.
                        $result = ['success' => false, 'expired' => false];
                    }
                    $status = $result['success'] ? 'sent' : 'failed';
                    DB::table('push_deliveries')->where('device_id', $device->id)->where('date', $event['date'])->whereIn('kind', $kinds)->update(['status' => $status, 'sent_at' => $result['success'] ? now() : null, 'updated_at' => now()]);
                    $device->update(['last_status' => $status, ...($result['success'] ? ['last_success_at' => now()] : []), ...($result['expired'] ? ['enabled' => false] : [])]);
                    if ($result['success']) {
                        $sent++;
                    }
                }
            }
        }

        return $sent;
    }
}
