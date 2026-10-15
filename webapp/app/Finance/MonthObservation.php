<?php

namespace App\Finance;

use Illuminate\Support\Facades\DB;

final class MonthObservation
{
    public function invalidate(array $dates): void
    {
        foreach (array_unique(array_filter($dates)) as $date) {
            DB::table('month_observations')->where('year', (int) substr($date, 0, 4))->where('month', (int) substr($date, 5, 2))->update(['complete' => false, 'updated_at' => now()]);
        }
    }
}
