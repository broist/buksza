<?php

namespace App\Finance;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

final class Insights
{
    public function forReport(array $report, array $recurring): array
    {
        return ['budgets' => array_values(array_filter($report['categories'], fn ($category) => $category['budgetState'] !== 'none')), 'changes' => $recurring['changes']];
    }

    public function onDate(string $date): array
    {
        $year = (int) substr($date, 0, 4);
        $month = (int) substr($date, 5, 2);
        $categories = Category::orderBy('position')->get()->toArray();
        $plans = [];
        foreach ($categories as $category) {
            for ($period = 1; $period <= 12; $period++) {
                $plans[$period][$category['id']] = (int) $category['default_amount'];
            }
        }
        foreach (DB::table('monthly_plans')->where('year', $year)->get() as $row) {
            $plans[$row->month][$row->category_id] = $row->amount;
        }
        // Read-only: an evening reminder must not create plans or include future records.
        $report = app(Calculator::class)->year($year, $categories, $plans, Transaction::whereBetween('date', ["$year-01-01", $date])->get()->toArray(), (array) DB::table('year_settings')->where('year', $year)->first(), $date);
        $recurring = app(RecurringStore::class)->month($year, $month);
        $recurring['changes'] = array_values(array_filter($recurring['changes'], fn ($change) => $change['record_date'] <= $date));

        return $this->forReport($report['months'][$month], $recurring);
    }
}
