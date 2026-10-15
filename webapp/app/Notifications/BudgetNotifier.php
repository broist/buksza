<?php

namespace App\Notifications;

use App\Finance\Insights;
use App\Models\PushDevice;
use Illuminate\Support\Facades\DB;

final class BudgetNotifier
{
    public function __construct(private PushTransport $transport) {}

    private function devices()
    {
        return PushDevice::where('user_id', auth()->id())->where('enabled', true)
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('reminder_settings')->whereColumn('reminder_settings.user_id', 'push_devices.user_id')->where('instant_budget_enabled', true));
    }

    public function before(string $date): ?array
    {
        try {
            if (! str_starts_with($date, now()->format('Y-m-')) || $date > now()->toDateString() || ! str_starts_with(config('app.url'), 'https://') || ! DB::table('push_credentials')->where('id', 1)->exists() || ! $this->devices()->exists()) {
                return null;
            }

            return app(Insights::class)->onDate(now()->toDateString())['budgets'];
        } catch (\Throwable) {
            return null;
        }
    }

    /** Call after the financial write/transaction completes; push failure cannot undo that write. */
    public function after(?array $before): void
    {
        if ($before === null) {
            return;
        }
        try {
            $date = now()->toDateString();
            $previous = array_column($before, null, 'id');
            foreach (app(Insights::class)->onDate($date)['budgets'] as $category) {
                if ($category['available'] <= 0 || $category['spent'] < $category['available']) {
                    continue;
                }
                $old = $previous[$category['id']] ?? null;
                if ($old && $old['available'] > 0 && $old['spent'] >= $old['available']) {
                    continue;
                }
                foreach ($this->devices()->get() as $device) {
                    $key = ['device_id' => $device->id, 'date' => substr($date, 0, 7).'-01', 'kind' => 'budget:'.$category['id']];
                    if (! DB::table('push_deliveries')->insertOrIgnore([...$key, 'status' => 'pending', 'attempted_at' => now(), 'created_at' => now(), 'updated_at' => now()])) {
                        continue;
                    }
                    try {
                        $result = $this->transport->send($device, [
                            'title' => $category['spent'] > $category['available'] ? 'Túllépted a kategóriakeretet' : 'Kategóriakeret elérve',
                            'body' => mb_substr($category['name'], 0, 120).': a havi keret elfogyott. Részletek a Bukszában.',
                            'tag' => 'buksza-budget-'.substr($date, 0, 7).'-'.$category['id'],
                            'url' => '/honap?year='.substr($date, 0, 4).'&month='.(int) substr($date, 5, 2),
                        ]);
                    } catch (\Throwable) {
                        $result = ['success' => false, 'expired' => false];
                    }
                    $status = $result['success'] ? 'sent' : 'failed';
                    DB::table('push_deliveries')->where($key)->update(['status' => $status, 'sent_at' => $result['success'] ? now() : null, 'updated_at' => now()]);
                    $device->update(['last_status' => $status, ...($result['success'] ? ['last_success_at' => now()] : []), ...($result['expired'] ? ['enabled' => false] : [])]);
                }
            }
        } catch (\Throwable) {
            // A saved financial record remains saved even when push is unavailable.
        }
    }
}
