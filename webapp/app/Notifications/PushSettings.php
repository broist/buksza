<?php

namespace App\Notifications;

use App\Models\PushDevice;
use Illuminate\Support\Facades\DB;

final class PushSettings
{
    public function forUser(int $user): array
    {
        $settings = (array) DB::table('reminder_settings')->where('user_id', $user)->first();
        $key = DB::table('push_credentials')->where('id', 1)->value('public_key');

        return [
            'daily_enabled' => (bool) ($settings['daily_enabled'] ?? true), 'daily_time' => $settings['daily_time'] ?? '19:00',
            'anna_enabled' => (bool) ($settings['anna_enabled'] ?? true), 'anna_time' => $settings['anna_time'] ?? '19:00',
            'insights_enabled' => (bool) ($settings['insights_enabled'] ?? true),
            'instant_budget_enabled' => (bool) ($settings['instant_budget_enabled'] ?? true),
            'public_key' => $key, 'ready' => (bool) $key && str_starts_with(config('app.url'), 'https://'),
            'heartbeat' => DB::table('scheduler_heartbeats')->where('name', 'reminders')->value('last_seen_at'),
            'devices' => PushDevice::where('user_id', $user)->get(['id', 'device_id', 'name', 'enabled', 'last_status', 'last_success_at'])->toArray(),
        ];
    }
}
