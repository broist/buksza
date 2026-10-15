<?php

namespace App\Operations;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class SystemStatus
{
    public function forUser(int $user): array
    {
        $now = CarbonImmutable::now('UTC');
        $seen = DB::table('scheduler_heartbeats')->where('name', 'reminders')->value('last_seen_at');
        $heartbeat = $seen ? CarbonImmutable::parse($seen, 'UTC') : null;
        $age = $heartbeat ? $heartbeat->diffInSeconds($now, false) : null;
        $attempt = $this->backupFile('backup.json', $now);
        $success = $this->backupFile('last-success.json', $now);
        $scheduled = $this->backupFile('scheduled-success.json', $now);
        $backupState = 'unknown';
        if ($attempt) {
            $backupState = match ($attempt['status']) {
                'failed' => 'failed',
                'running' => CarbonImmutable::parse($attempt['started_at'])->diffInSeconds($now, false) <= 1800 ? 'running' : 'stalled',
                default => ! $success ? 'unknown' : (CarbonImmutable::parse($success['finished_at'])->diffInSeconds($now, false) <= 93600 ? 'healthy' : 'stale'),
            };
        }

        return [
            'checked_at' => $now->toIso8601String(),
            'scheduler' => ['state' => $age === null ? 'unknown' : ($age >= 0 && $age <= 180 ? 'healthy' : 'stale'), 'last_seen_at' => $heartbeat?->toIso8601String()],
            'backup' => ['state' => $backupState, 'scheduled_state' => ! $scheduled ? 'unknown' : (CarbonImmutable::parse($scheduled['finished_at'])->diffInSeconds($now, false) <= 93600 ? 'healthy' : 'stale'), 'attempt' => $attempt, 'last_success' => $success, 'last_scheduled_success' => $scheduled],
            'deliveries' => DB::table('push_deliveries')->join('push_devices', 'push_devices.id', '=', 'push_deliveries.device_id')->where('push_devices.user_id', $user)
                ->orderByDesc('push_deliveries.attempted_at')->orderByDesc('push_deliveries.id')->limit(20)
                ->get(['push_deliveries.id', 'push_devices.name as device_name', 'push_deliveries.kind', 'push_deliveries.status', 'push_deliveries.attempted_at', 'push_deliveries.sent_at'])
                ->map(fn ($row) => [...(array) $row, 'attempted_at' => $row->attempted_at ? CarbonImmutable::parse($row->attempted_at, config('app.timezone'))->toIso8601String() : null, 'sent_at' => $row->sent_at ? CarbonImmutable::parse($row->sent_at, config('app.timezone'))->toIso8601String() : null])->all(),
        ];
    }

    private function backupFile(string $name, CarbonImmutable $now): ?array
    {
        $path = rtrim(config('operations.backup_status_directory'), '/').'/'.$name;
        clearstatcache(true, $path);
        try {
            if (! is_file($path) || ! is_readable($path) || filesize($path) > 2048) {
                return null;
            }
            $data = json_decode(file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
            if (($data['version'] ?? null) !== 1 || ! in_array($data['status'] ?? null, ['running', 'failed', 'succeeded'], true) || ! in_array($data['trigger'] ?? null, ['manual', 'scheduled'], true)) {
                return null;
            }
            foreach (['started_at', 'finished_at'] as $key) {
                $value = $data[$key] ?? null;
                if ($key === 'finished_at' && $value === null && $data['status'] === 'running') {
                    continue;
                }
                if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value)) {
                    return null;
                }
                $time = CarbonImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, 'UTC');
                if ($time->format('Y-m-d\TH:i:s\Z') !== $value || $time->greaterThan($now->addMinutes(5))) {
                    return null;
                }
            }
            if ($data['status'] === 'succeeded' && (! is_int($data['bytes'] ?? null) || $data['bytes'] <= 0)) {
                return null;
            }
            if (isset($data['finished_at']) && $data['finished_at'] < $data['started_at']) {
                return null;
            }
            if ($name !== 'backup.json' && ($data['status'] !== 'succeeded' || ($name === 'scheduled-success.json' && $data['trigger'] !== 'scheduled'))) {
                return null;
            }

            // Only operational metadata is returned; never paths, configuration or log contents.
            return ['status' => $data['status'], 'trigger' => $data['trigger'], 'started_at' => $data['started_at'], 'finished_at' => $data['finished_at'] ?? null, 'bytes' => $data['status'] === 'succeeded' ? $data['bytes'] : null];
        } catch (\Throwable) {
            return null;
        }
    }
}
