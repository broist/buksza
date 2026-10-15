<?php

namespace App\Finance;

use App\Models\Investment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class PhaseFourReports
{
    public function pockets(int $year, array $transactions, array $accounts): array
    {
        $transactions = array_values(array_filter($transactions, fn ($tx) => str_starts_with($tx['date'], "$year-")));
        $rows = app(Calculator::class)->familyTransactions($transactions);
        $people = ['Isti', 'Anna'];
        $totals = $monthly = [];
        foreach ($rows as $tx) {
            if ($tx['type'] !== 'expense' || $tx['category_id'] != 21 || ($tx['informational'] ?? false)) {
                continue;
            }
            $person = $tx['person'] ?: 'közös';
            $people[] = $person;
            $month = (int) substr($tx['date'], 5, 2);
            $monthly[$month][$person] = ($monthly[$month][$person] ?? 0) + $tx['amount'];
            $totals[$person] = ($totals[$person] ?? 0) + $tx['amount'];
        }
        $modes = DB::table('account_modes')->orderBy('effective_date')->get();
        $internal = [];
        foreach ($transactions as $tx) {
            if (! $tx['informational'] || $tx['type'] !== 'expense') {
                continue;
            }
            $mode = collect($accounts)->firstWhere('id', $tx['account_id'])['mode'];
            foreach ($modes as $version) {
                if ($version->account_id == $tx['account_id'] && $version->effective_date <= $tx['date']) {
                    $mode = $version->mode;
                }
            }
            if ($mode === 'pocket') {
                $internal[] = $tx;
            }
        }

        return ['people' => array_values(array_unique($people)), 'months' => $monthly, 'totals' => $totals, 'total' => array_sum($totals), 'internalTotal' => array_sum(array_column($internal, 'amount')), 'internal' => $internal];
    }

    public function investments(int $year, int $month, array $transactions): array
    {
        $through = CarbonImmutable::create($year, $month, 1)->endOfMonth()->toDateString();
        $all = Investment::orderBy('id')->get()->map(function ($investment) use ($year, $through, $transactions) {
            $plans = DB::table('investment_plans')->where('investment_id', $investment->id)->orderBy('effective_date')->get();
            $values = DB::table('investment_values')->where('investment_id', $investment->id)->orderByDesc('date')->get();
            $payments = array_values(array_filter($transactions, fn ($tx) => ($tx['investment_id'] ?? null) == $investment->id && $tx['type'] === 'child_saving'));
            $months = [];
            for ($m = 1; $m <= 12; $m++) {
                $end = CarbonImmutable::create($year, $m, 1)->endOfMonth()->toDateString();
                $prefix = substr($end, 0, 7);
                $plan = 0;
                foreach ($plans as $version) {
                    if ($version->effective_date <= $end && $investment->start_date <= $end) {
                        $plan = (int) $version->amount;
                    }
                }
                $actual = array_sum(array_column(array_filter($payments, fn ($tx) => str_starts_with($tx['date'], $prefix)), 'amount'));
                $months[$m] = ['plan' => $plan, 'actual' => $actual, 'difference' => $actual - $plan];
            }
            $latest = $values->first(fn ($v) => $v->date <= $through);

            return [...$investment->toArray(), 'plans' => $plans, 'values' => $values, 'latest' => $latest, 'months' => $months, 'annualPlan' => array_sum(array_column($months, 'plan')), 'annualActual' => array_sum(array_column($months, 'actual')), 'contributed' => $investment->start_date > $through ? null : $investment->opening_amount + array_sum(array_column(array_filter($payments, fn ($tx) => $tx['date'] <= $through), 'amount')), 'payments' => $payments];
        })->all();
        $yearPayments = array_values(array_filter($transactions, fn ($tx) => $tx['type'] === 'child_saving' && str_starts_with($tx['date'], "$year-")));
        $started = array_values(array_filter($all, fn ($row) => $row['start_date'] <= $through));
        $unknown = count(array_filter($started, fn ($row) => $row['latest'] === null));

        return ['items' => $all, 'through' => $through, 'annualActual' => array_sum(array_column($yearPayments, 'amount')), 'unassigned' => array_values(array_filter($yearPayments, fn ($tx) => ! ($tx['investment_id'] ?? null))), 'value' => $unknown || ! $started ? null : array_sum(array_map(fn ($row) => (int) $row['latest']->amount, $started)), 'unknown' => $unknown];
    }
}
