<?php

namespace App\Finance;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

final class PlanStore
{
    public function ensureYear(int $year): void
    {
        DB::table('year_settings')->insertOrIgnore(['year' => $year, 'opening_amount' => 0, 'savings_rate' => '10', 'created_at' => now(), 'updated_at' => now()]);
        $rows = [];
        foreach (Category::all() as $category) {
            for ($month = 1; $month <= 12; $month++) {
                $rows[] = ['year' => $year, 'month' => $month, 'category_id' => $category->id, 'amount' => $category->default_amount, 'is_override' => false, 'created_at' => now(), 'updated_at' => now()];
            }
        }
        DB::table('monthly_plans')->insertOrIgnore($rows);
    }
}
